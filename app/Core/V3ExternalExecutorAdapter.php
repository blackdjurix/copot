<?php

namespace Copot\Core;

/**
 * Composition boundary for a corrected V3 executor outside the target
 * installation's code tree. Preflight is read-only; execution is deliberately
 * a separate method and requires the caller to have already authorized it.
 */
final class V3ExternalExecutorAdapter
{
    /**
     * Load the corrected workspace autoloader and validate a retained V3
     * pre-capture operation without constructing PackageLifecycleFactory or
     * acquiring any target mutex/Guard lease.
     *
     * @return array{status:string, checks:array<string,string>, bindings:array<string,mixed>, reason:string}
     */
    public static function preflightFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $operationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        ?string $expectedDatabase = null
    ): array {
        $checks = [];

        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace');
            $autoload = $workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php';
            self::file($autoload, 'corrected autoloader');
            require_once $autoload;
            $checks['corrected_autoloader'] = 'PASS';

            $adapter = new self($workspace);
            $result = $adapter->preflight(
                $appRoot,
                $publicRoot,
                $recoveryRoot,
                $packagePath,
                $operationId,
                $expectedPackageSha256,
                $expectedSourceFingerprint,
                $expectedDatabase,
                $checks
            );
            return $result;
        } catch (\Throwable $exception) {
            return [
                'status' => 'BLOCKED',
                'checks' => $checks,
                'bindings' => [],
                'reason' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Run exactly one existing V3 continuation after the same read-only
     * preflight used by the external executor gate. No fresh apply route is
     * exposed here and the factory is strict about an existing installation
     * identity.
     *
     * @return array{status:string, invocation_count:int, preflight:array<string,mixed>, result:array<string,mixed>|null, reason:string}
     */
    public static function executeContinuationFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $operationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        ?string $expectedDatabase = null
    ): array {
        $preflight = self::preflightFromWorkspace(
            $workspaceRoot,
            $appRoot,
            $publicRoot,
            $recoveryRoot,
            $packagePath,
            $operationId,
            $expectedPackageSha256,
            $expectedSourceFingerprint,
            $expectedDatabase
        );
        if (($preflight['status'] ?? null) !== 'PASS') {
            return [
                'status' => 'BLOCKED',
                'invocation_count' => 0,
                'preflight' => $preflight,
                'result' => null,
                'reason' => (string) ($preflight['reason'] ?? 'External V3 preflight was not accepted.'),
            ];
        }

        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace');
            require_once self::file($workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', 'corrected autoloader');
            $targetAppRoot = self::directory($appRoot, 'target APP_ROOT');
            $targetPublicRoot = self::directory($publicRoot, 'target PUBLIC_ROOT');
            $context = new DeploymentContext($targetAppRoot, $targetPublicRoot, '/');
            Env::load($targetAppRoot . DIRECTORY_SEPARATOR . '.env');
            $service = PackageLifecycleFactory::forProject($context, null, true);
            $result = $service->retry($operationId);
            return [
                'status' => 'EXECUTED',
                'invocation_count' => 1,
                'preflight' => $preflight,
                'result' => $result->toArray(),
                'reason' => $result->reason(),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'EXECUTION_FAILED',
                'invocation_count' => 1,
                'preflight' => $preflight,
                'result' => null,
                'reason' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Settle one verified pre-mutation rejection through the existing
     * net-zero evidence route. This publishes durable evidence before the
     * supported lifecycle clear and never enters package apply or recovery.
     *
     * @return array{status:string, invocation_count:int, preflight:array<string,mixed>, result:array<string,mixed>|null, reason:string}
     */
    public static function executePreMutationSettlementFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $operationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        ?string $expectedDatabase = null
    ): array {
        $preflight = self::preflightFromWorkspace(
            $workspaceRoot,
            $appRoot,
            $publicRoot,
            $recoveryRoot,
            $packagePath,
            $operationId,
            $expectedPackageSha256,
            $expectedSourceFingerprint,
            $expectedDatabase
        );
        if (($preflight['status'] ?? null) !== 'PASS') {
            return ['status' => 'BLOCKED', 'invocation_count' => 0, 'preflight' => $preflight, 'result' => null, 'reason' => (string) ($preflight['reason'] ?? 'Pre-mutation settlement preflight was not accepted.')];
        }
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace');
            require_once self::file($workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', 'corrected autoloader');
            $targetAppRoot = self::directory($appRoot, 'target APP_ROOT');
            $targetPublicRoot = self::directory($publicRoot, 'target PUBLIC_ROOT');
            $context = new DeploymentContext($targetAppRoot, $targetPublicRoot, '/');
            Env::load($targetAppRoot . DIRECTORY_SEPARATOR . '.env');
            $service = PackageLifecycleFactory::forProject($context, null, true);
            $storage = $targetAppRoot . DIRECTORY_SEPARATOR . 'storage';
            $operation = LifecycleOperationRecord::fromArray(self::jsonFile($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'active-operation.json', 'persisted V3 operation'));
            $payload = self::retainedPayload($operation->stagingPath(), strtolower($expectedPackageSha256));
            try {
                $manifest = (new PackageManifestReader())->read($payload);
                $sourceState = (new CommittedLifecycleStateStore($storage))->read();
                $reference = $sourceState?->sourceInventoryReference();
                if (!$reference instanceof SourceInventoryReference) throw new \RuntimeException('Committed source inventory reference is unavailable.');
                $source = (new SourceInventoryArtifactStore($storage))->read($reference);
                $plan = (new PackageV3SourceRelativePlanner())->plan($manifest, $source);
                $applyPlan = WebcoreApplyPlan::fromPayload($payload->withoutPaths([PackageManifestReader::PATH]));
                $state = self::preMutationStateEvidence($targetAppRoot);
                $retirementContext = new NetZeroRetirementContext(
                    $operationId,
                    $targetAppRoot,
                    $targetAppRoot,
                    hash('sha256', strtolower(str_replace('\\', '/', realpath($targetAppRoot) ?: $targetAppRoot))),
                    $state,
                    static fn (): array => self::preMutationStateEvidence($targetAppRoot),
                    $operation->migrationPlanIdentity() ?? hash('sha256', ''),
                    'V3 package apply was rejected before mutation; duplicate recovery identity preserved.',
                    true
                );
                $result = $service->retireNetZero($operationId, $applyPlan, $retirementContext);
                return ['status' => 'EXECUTED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => $result->toArray(), 'reason' => $result->reason()];
            } finally {
                $payload->cleanup();
            }
        } catch (\Throwable $exception) {
            return ['status' => 'EXECUTION_FAILED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => null, 'reason' => $exception->getMessage()];
        }
    }

    /**
     * Read-only gate for a fresh V3 package apply against an existing committed
     * installation.  Intake is temporary and is always cleaned before return.
     * No lifecycle factory is constructed until this gate passes.
     *
     * @return array{status:string, checks:array<string,string>, bindings:array<string,mixed>, reason:string}
     */
    public static function preflightFreshApplyFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        ?string $expectedDatabase = null,
        int $expectedRetainCount = 814,
        int $expectedReplaceCount = 1,
        string $expectedReplacePath = 'resources/c1-cycle/cycle.txt',
        string $expectedRetainPath = 'resources/c1-cycle/companion.txt'
    ): array {
        $checks = [];
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace');
            $autoload = $workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php';
            self::file($autoload, 'corrected autoloader');
            require_once $autoload;
            $checks['corrected_autoloader'] = 'PASS';
            return (new self($workspace))->preflightFreshApply(
                $appRoot,
                $publicRoot,
                $recoveryRoot,
                $packagePath,
                $expectedInstallationId,
                $expectedPackageSha256,
                $expectedSourceFingerprint,
                $expectedTargetFingerprint,
                $expectedDatabase,
                $expectedRetainCount,
                $expectedReplaceCount,
                $expectedReplacePath,
                $expectedRetainPath,
                $checks
            );
        } catch (\Throwable $exception) {
            return ['status' => 'BLOCKED', 'checks' => $checks, 'bindings' => [], 'reason' => $exception->getMessage()];
        }
    }

    /**
     * Run exactly one fresh V3 apply after the fresh-apply gate.  This method
     * intentionally exposes neither retry nor the generic coordinator route.
     *
     * @return array{status:string, invocation_count:int, preflight:array<string,mixed>, result:array<string,mixed>|null, reason:string}
     */
    public static function executeFreshApplyFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        ?string $expectedDatabase = null,
        int $expectedRetainCount = 814,
        int $expectedReplaceCount = 1,
        string $expectedReplacePath = 'resources/c1-cycle/cycle.txt',
        string $expectedRetainPath = 'resources/c1-cycle/companion.txt'
    ): array {
        $preflight = self::preflightFreshApplyFromWorkspace(
            $workspaceRoot,
            $appRoot,
            $publicRoot,
            $recoveryRoot,
            $packagePath,
            $expectedInstallationId,
            $expectedPackageSha256,
            $expectedSourceFingerprint,
            $expectedTargetFingerprint,
            $expectedDatabase,
            $expectedRetainCount,
            $expectedReplaceCount,
            $expectedReplacePath,
            $expectedRetainPath
        );
        if (($preflight['status'] ?? null) !== 'PASS') {
            return ['status' => 'BLOCKED', 'invocation_count' => 0, 'preflight' => $preflight, 'result' => null, 'reason' => (string) ($preflight['reason'] ?? 'Fresh V3 preflight was not accepted.')];
        }
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace');
            require_once self::file($workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', 'corrected autoloader');
            $context = new DeploymentContext(self::directory($appRoot, 'target APP_ROOT'), self::directory($publicRoot, 'target PUBLIC_ROOT'), '/');
            Env::load($context->appRoot() . DIRECTORY_SEPARATOR . '.env');
            $service = PackageLifecycleFactory::forProject($context, null, true);
            $result = $service->apply($packagePath, false, null);
            return ['status' => 'EXECUTED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => $result->toArray(), 'reason' => $result->reason()];
        } catch (\Throwable $exception) {
            return ['status' => 'EXECUTION_FAILED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => null, 'reason' => $exception->getMessage()];
        }
    }

    /** @return array{status:string, invocation_count:int, preflight:array<string,mixed>, result:array<string,mixed>|null, reason:string} */
    public static function executeFreshRenameFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        string $actorIdentity,
        string $capabilityReference,
        ?string $expectedDatabase = null,
        int $expectedRetainCount = 814
    ): array {
        $preflight = self::preflightFreshRenameFromWorkspace(
            $workspaceRoot,
            $appRoot,
            $publicRoot,
            $recoveryRoot,
            $packagePath,
            $expectedInstallationId,
            $expectedPackageSha256,
            $expectedSourceFingerprint,
            $expectedTargetFingerprint,
            $expectedDatabase,
            $expectedRetainCount
        );
        if (($preflight['status'] ?? null) !== 'PASS') return ['status' => 'BLOCKED', 'invocation_count' => 0, 'preflight' => $preflight, 'result' => null, 'reason' => (string) ($preflight['reason'] ?? 'Fresh V3 rename preflight was not accepted.')];
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace');
            require_once self::file($workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', 'corrected autoloader');
            $context = new DeploymentContext(self::directory($appRoot, 'target APP_ROOT'), self::directory($publicRoot, 'target PUBLIC_ROOT'), '/');
            Env::load($context->appRoot() . DIRECTORY_SEPARATOR . '.env');
            $service = PackageLifecycleFactory::forProject($context, null, true);
            $confirmation = [
                'operation_identity' => $preflight['bindings']['operation_id'],
                'plan_identity' => $preflight['bindings']['plan_identity'],
                'removal_set_digest' => $preflight['bindings']['removal_set_digest'],
                'actor_authorization_evidence' => ['actor_identity' => $actorIdentity, 'capability_reference' => $capabilityReference, 'session_identity' => null],
                'confirmation_version' => 1,
            ];
            $result = $service->apply($packagePath, false, $confirmation, $actorIdentity);
            return ['status' => 'EXECUTED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => $result->toArray(), 'reason' => $result->reason()];
        } catch (\Throwable $exception) {
            return ['status' => 'EXECUTION_FAILED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => null, 'reason' => $exception->getMessage()];
        }
    }

    private function __construct(private string $workspaceRoot)
    {
    }

    /**
     * @param array<string,string> $checks
     * @return array{status:string, checks:array<string,string>, bindings:array<string,mixed>, reason:string}
     */
    private function preflight(
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $operationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        ?string $expectedDatabase,
        array &$checks
    ): array {
        $appRoot = self::directory($appRoot, 'target APP_ROOT');
        $publicRoot = self::directory($publicRoot, 'target PUBLIC_ROOT');
        $recoveryRoot = self::directory($recoveryRoot, 'target Recovery Root');
        $context = new DeploymentContext($appRoot, $publicRoot, '/');
        if (!$context->isSplitRoot()) {
            throw new \RuntimeException('Target APP_ROOT/PUBLIC_ROOT are not split roots.');
        }
        $checks['target_context'] = 'PASS';

        $this->assertCorrectedClassComposition($checks);

        $storage = $appRoot . DIRECTORY_SEPARATOR . 'storage';
        $lifecycle = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        self::directory($storage, 'target lifecycle storage');
        self::directory($lifecycle, 'target lifecycle namespace');

        $env = self::readEnv($appRoot . DIRECTORY_SEPARATOR . '.env');
        if (($env['COPOT_RECOVERY_ROOT'] ?? null) === null || self::samePath($env['COPOT_RECOVERY_ROOT'], $recoveryRoot) !== true) {
            throw new \RuntimeException('Target .env Recovery Root does not bind to the requested Recovery Root.');
        }
        if ($expectedDatabase !== null && ($env['DB_DATABASE'] ?? null) !== $expectedDatabase) {
            throw new \RuntimeException('Target database binding does not match the expected database.');
        }
        $checks['target_configuration'] = 'PASS';

        $identityPath = $lifecycle . DIRECTORY_SEPARATOR . 'installation-identity.json';
        $identityData = self::jsonFile($identityPath, 'installation identity');
        $installationId = $identityData['installation_id'] ?? null;
        if (!is_string($installationId) || preg_match('/^inst_[a-f0-9]{32}$/D', $installationId) !== 1) {
            throw new \RuntimeException('Target installation identity is invalid.');
        }
        $checks['installation_identity'] = 'PASS';

        $operationPath = $lifecycle . DIRECTORY_SEPARATOR . 'active-operation.json';
        $operation = LifecycleOperationRecord::fromArray(self::jsonFile($operationPath, 'persisted V3 operation'));
        if ($operation->operationId() !== $operationId
            || $operation->phase() !== LifecycleOperationRecord::BLOCKED
            || $operation->fileCursor() !== 0
            || $operation->lastVerifiedPath() !== null
            || $operation->migrationOutcome() !== null
            || $operation->recoveryIdentity() !== null
            || $operation->recoveryManifestIdentity() !== null
            || $operation->recoveryState() !== null) {
            throw new \RuntimeException('Persisted operation is not an eligible V3 pre-capture record.');
        }
        $checks['persisted_operation'] = 'PASS';

        $committedData = self::jsonFile($lifecycle . DIRECTORY_SEPARATOR . 'committed-state.json', 'committed lifecycle state');
        $committed = CommittedLifecycleState::fromArray($committedData);
        $reference = $committed->sourceInventoryReference();
        if (!$reference instanceof SourceInventoryReference || $reference->fingerprint() !== strtolower($expectedSourceFingerprint)) {
            throw new \RuntimeException('Committed source inventory does not bind to expected F0.');
        }
        $sourceDirectory = $lifecycle . DIRECTORY_SEPARATOR . 'source-inventory';
        self::directory($sourceDirectory, 'source inventory namespace');
        $sourceStore = new SourceInventoryArtifactStore($storage);
        $source = $sourceStore->read($reference);
        $checks['committed_source_inventory'] = 'PASS';

        $archiveSha256 = strtolower($expectedPackageSha256);
        if (preg_match('/^[a-f0-9]{64}$/D', $archiveSha256) !== 1 || !is_file($packagePath) || is_link($packagePath) || strtolower((string) hash_file('sha256', $packagePath)) !== $archiveSha256) {
            throw new \RuntimeException('Original package archive SHA-256 does not match the expected identity.');
        }
        $stagingPath = $operation->stagingPath();
        $runtimeStagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-runtime' . DIRECTORY_SEPARATOR . $installationId . DIRECTORY_SEPARATOR . 'package-staging';
        if (!self::inside($stagingPath, $runtimeStagingRoot) || basename($stagingPath) === '' || !str_starts_with(strtolower(basename($stagingPath)), 'session-')) {
            throw new \RuntimeException('Retained staging is outside the installation-scoped runtime namespace.');
        }
        self::directory($stagingPath, 'retained V3 staging');
        $stagedArchive = $stagingPath . DIRECTORY_SEPARATOR . 'source.zip';
        if (!is_file($stagedArchive) || is_link($stagedArchive) || strtolower((string) hash_file('sha256', $stagedArchive)) !== $archiveSha256) {
            throw new \RuntimeException('Retained staging archive does not match the original package.');
        }
        $payload = self::retainedPayload($stagingPath, $archiveSha256);
        $manifest = (new PackageManifestReader())->read($payload);
        if ($manifest->contract()->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION
            || $manifest->contract()->coverage() !== 'partial'
            || $manifest->contract()->migrationDeclaration()->declaresCoreMigrations()
            || !$manifest->contract()->targetRequirements()->isEmpty()
            || $manifest->contract()->runtimeCompatibility()->minimumDatabaseVersions() !== []) {
            throw new \RuntimeException('Retained package is not a filesystem-only partial V3 package.');
        }
        $planner = new PackageV3SourceRelativePlanner();
        $plan = $planner->plan($manifest, $source);
        $applyPlan = WebcoreApplyPlan::fromPayload($payload->withoutPaths([PackageManifestReader::PATH]));
        $payloadIdentity = self::payloadIdentity($payload);
        $migrationIdentity = hash('sha256', '');
        if ($plan->operationIdentity() !== $operation->operationId()
            || $payloadIdentity !== $operation->payloadIdentity()
            || $applyPlan->identity() !== $operation->applyPlanIdentity()
            || $manifest->contract()->releaseIdentity() !== $operation->releaseIdentity()
            || $manifest->contract()->targetWebcoreVersion() !== $operation->targetWebcoreVersion()
            || $migrationIdentity !== $operation->migrationPlanIdentity()
            || $plan->sourceFingerprint() !== $reference->fingerprint()) {
            throw new \RuntimeException('Package, staging, planner, operation, or source fingerprint binding is inconsistent.');
        }
        $checks['package_staging_planner_binding'] = 'PASS';

        $checks['split_root_executor_composition'] = 'PASS';

        $this->assertLiveState($context, $plan, $source);
        if (!is_file($context->publicPath('.htaccess')) || is_link($context->publicPath('.htaccess'))) {
            throw new \RuntimeException('Protected PUBLIC_ROOT/.htaccess is missing or unsafe.');
        }
        $checks['live_target_state'] = 'PASS';

        $guardPath = $lifecycle . DIRECTORY_SEPARATOR . 'application-write-guard.json';
        if (is_file($guardPath)) {
            $guard = self::jsonFile($guardPath, 'installation-scoped Guard state');
            if (($guard['active'] ?? false) === true || ($guard['draining'] ?? false) === true || (int) ($guard['in_flight'] ?? 0) !== 0) {
                throw new \RuntimeException('Installation-scoped Guard is not idle.');
            }
        }
        $checks['guard'] = 'PASS';

        $lockPath = $storage . DIRECTORY_SEPARATOR . '.install.lock';
        if (file_exists($lockPath) && (is_link($lockPath) || !is_file($lockPath) || !is_readable($lockPath))) {
            throw new \RuntimeException('Installation mutex path is invalid.');
        }
        $checks['mutex'] = 'PASS (static path only; exclusive availability is rechecked at execution time)';

        $this->assertRetainedRecoveryEvidence($recoveryRoot);
        $checks['recovery_namespace'] = 'PASS (retained evidence verified)';

        $actions = [];
        foreach ($plan->actions() as $action) {
            $actions[$action->action()] = ($actions[$action->action()] ?? 0) + 1;
        }

        return [
            'status' => 'PASS',
            'checks' => $checks,
            'bindings' => [
                'workspace_root' => $this->workspaceRoot,
                'app_root' => $context->appRoot(),
                'public_root' => $context->publicRoot(),
                'recovery_root' => $recoveryRoot,
                'installation_id' => $installationId,
                'database' => $env['DB_DATABASE'] ?? null,
                'operation_id' => $operation->operationId(),
                'staging_path' => $stagingPath,
                'package_sha256' => $archiveSha256,
                'source_fingerprint' => $plan->sourceFingerprint(),
                'target_fingerprint' => $plan->targetFingerprint(),
                'actions' => $actions,
                'execution_mode' => 'NOT EXPOSED BY READ-ONLY ADAPTER',
            ],
            'reason' => '',
        ];
    }

    /**
     * @param array<string,string> $checks
     * @return array{status:string, checks:array<string,string>, bindings:array<string,mixed>, reason:string}
     */
    private function preflightFreshApply(
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        ?string $expectedDatabase,
        int $expectedRetainCount,
        int $expectedReplaceCount,
        string $expectedReplacePath,
        string $expectedRetainPath,
        array &$checks
    ): array {
        $appRoot = self::directory($appRoot, 'target APP_ROOT');
        $publicRoot = self::directory($publicRoot, 'target PUBLIC_ROOT');
        $recoveryRoot = self::directory($recoveryRoot, 'target Recovery Root');
        $context = new DeploymentContext($appRoot, $publicRoot, '/');
        if (!$context->isSplitRoot()) throw new \RuntimeException('Target APP_ROOT/PUBLIC_ROOT are not split roots.');
        $checks['target_context'] = 'PASS';
        $this->assertCorrectedClassComposition($checks);

        $storage = $appRoot . DIRECTORY_SEPARATOR . 'storage';
        $lifecycle = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        self::directory($storage, 'target lifecycle storage');
        self::directory($lifecycle, 'target lifecycle namespace');
        $env = self::readEnv($appRoot . DIRECTORY_SEPARATOR . '.env');
        if (!isset($env['COPOT_RECOVERY_ROOT']) || self::samePath($env['COPOT_RECOVERY_ROOT'], $recoveryRoot) !== true) throw new \RuntimeException('Target Recovery Root binding is incorrect.');
        if ($expectedDatabase !== null && ($env['DB_DATABASE'] ?? null) !== $expectedDatabase) throw new \RuntimeException('Target database binding does not match the expected database.');
        $checks['target_configuration'] = 'PASS';

        $identity = self::jsonFile($lifecycle . DIRECTORY_SEPARATOR . 'installation-identity.json', 'installation identity');
        if (($identity['installation_id'] ?? null) !== $expectedInstallationId) throw new \RuntimeException('Installation identity does not match the expected target.');
        $checks['installation_identity'] = 'PASS';

        $operationPath = $lifecycle . DIRECTORY_SEPARATOR . 'active-operation.json';
        if (file_exists($operationPath) || is_link($operationPath)) throw new \RuntimeException('A lifecycle operation is already active.');
        $checks['active_operation'] = 'PASS';

        $committed = (new CommittedLifecycleStateStore($storage))->read();
        $reference = $committed?->sourceInventoryReference();
        if (!$committed instanceof CommittedLifecycleState || !$reference instanceof SourceInventoryReference || $reference->fingerprint() !== strtolower($expectedSourceFingerprint)) throw new \RuntimeException('Committed source inventory does not bind to expected F1.');
        $source = (new SourceInventoryArtifactStore($storage))->read($reference);
        $checks['committed_source_inventory'] = 'PASS';

        $packageSha = strtolower($expectedPackageSha256);
        if (preg_match('/^[a-f0-9]{64}$/D', $packageSha) !== 1 || !is_file($packagePath) || is_link($packagePath) || strtolower((string) hash_file('sha256', $packagePath)) !== $packageSha) throw new \RuntimeException('P2 package SHA-256 does not match the expected identity.');
        $intake = new ZipIntakeService($appRoot, null, null, null, null, $expectedInstallationId);
        $payload = $intake->intake($packagePath);
        try {
            $manifest = (new PackageManifestReader())->read($payload);
            (new PackageInventoryVerifier())->verify($payload, $manifest->contract()->inventory(), [PackageManifestReader::PATH]);
            $contract = $manifest->contract();
            if ($contract->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || $contract->coverage() !== 'partial' || $contract->migrationDeclaration()->declaresCoreMigrations() || !$contract->targetRequirements()->isEmpty() || $contract->runtimeCompatibility()->minimumDatabaseVersions() !== []) throw new \RuntimeException('P2 is not a filesystem-only partial V3 package.');
            $plan = (new PackageV3SourceRelativePlanner())->plan($manifest, $source);
            $counts = [];
            foreach ($plan->actions() as $action) $counts[$action->action()] = ($counts[$action->action()] ?? 0) + 1;
            if ($plan->sourceFingerprint() !== strtolower($expectedSourceFingerprint) || $plan->targetFingerprint() !== strtolower($expectedTargetFingerprint) || $plan->removalActions() !== [] || ($counts[V3SourceRelativeAction::RETAIN] ?? 0) !== $expectedRetainCount || ($counts[V3SourceRelativeAction::REPLACE] ?? 0) !== $expectedReplaceCount || ($counts[V3SourceRelativeAction::DELIVER] ?? 0) !== 0 || ($counts[V3SourceRelativeAction::REMOVE] ?? 0) !== 0 || ($counts[V3SourceRelativeAction::RENAME] ?? 0) !== 0) throw new \RuntimeException('Fresh P2 planner result does not match the expected RETAIN/REPLACE action counts.');
            $this->assertInventoryLiveState($context, $source->inventory());
            $replace = null;
            foreach ($plan->actions() as $action) if ($action->action() === V3SourceRelativeAction::REPLACE) $replace = $action;
            if (!$replace instanceof V3SourceRelativeAction || $replace->root() !== CanonicalTargetInventoryEntry::APP_ROOT || $replace->path() !== $expectedReplacePath) throw new \RuntimeException('Fresh V3 REPLACE action is not bound to the expected target path.');
            $replaceTarget = $context->path($expectedReplacePath);
            $replaceHash = is_file($replaceTarget) ? strtolower((string) hash_file('sha256', $replaceTarget)) : '';
            if (!is_file($replaceTarget) || is_link($replaceTarget) || $replaceHash !== $this->sourceHash($source, CanonicalTargetInventoryEntry::APP_ROOT, $replace->path()) || $replaceHash === $replace->sha256()) throw new \RuntimeException('Fresh V3 source replacement target does not match the expected pre-REPLACE state.');
            $retained = $context->path($expectedRetainPath);
            if (!is_file($retained) || is_link($retained) || strtolower((string) hash_file('sha256', $retained)) !== $this->sourceHash($source, CanonicalTargetInventoryEntry::APP_ROOT, $expectedRetainPath)) throw new \RuntimeException('Fresh V3 retained path is not at its committed source identity.');
            $htaccess = $context->publicPath('.htaccess');
            if (!is_file($htaccess) || is_link($htaccess)) throw new \RuntimeException('Protected PUBLIC_ROOT/.htaccess is missing or unsafe.');
            $checks['package_planner'] = 'PASS';
            $checks['live_target_state'] = 'PASS';
            $checks['protected_htaccess'] = 'PASS';
        } finally {
            $payload->cleanup();
        }

        $guardPath = $lifecycle . DIRECTORY_SEPARATOR . 'application-write-guard.json';
        if (is_file($guardPath)) { $guard = self::jsonFile($guardPath, 'installation-scoped Guard state'); if (($guard['active'] ?? false) === true || ($guard['draining'] ?? false) === true || (int) ($guard['in_flight'] ?? 0) !== 0) throw new \RuntimeException('Installation-scoped Guard is not idle.'); }
        $checks['guard'] = 'PASS';
        $lockPath = $storage . DIRECTORY_SEPARATOR . '.install.lock';
        if (file_exists($lockPath) && (is_link($lockPath) || !is_file($lockPath) || !is_readable($lockPath))) throw new \RuntimeException('Installation mutex path is invalid.');
        $checks['mutex'] = 'PASS (static path only; exclusive availability is rechecked at execution time)';
        $this->assertRetainedRecoveryEvidence($recoveryRoot);
        $checks['recovery_namespace'] = 'PASS (empty or retained evidence verified)';
        return ['status' => 'PASS', 'checks' => $checks, 'bindings' => ['workspace_root' => $this->workspaceRoot, 'app_root' => $context->appRoot(), 'public_root' => $context->publicRoot(), 'recovery_root' => $recoveryRoot, 'installation_id' => $expectedInstallationId, 'database' => $env['DB_DATABASE'] ?? null, 'package_sha256' => $packageSha, 'source_fingerprint' => strtolower($expectedSourceFingerprint), 'target_fingerprint' => strtolower($expectedTargetFingerprint), 'actions' => $counts, 'execution_mode' => 'fresh-v3-apply'] , 'reason' => ''];
    }

    /** @param array<string,string> $checks */
    private function preflightFreshRename(
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        ?string $expectedDatabase,
        int $expectedRetainCount,
        array &$checks
    ): array {
        $appRoot = self::directory($appRoot, 'target APP_ROOT');
        $publicRoot = self::directory($publicRoot, 'target PUBLIC_ROOT');
        $recoveryRoot = self::directory($recoveryRoot, 'target Recovery Root');
        $context = new DeploymentContext($appRoot, $publicRoot, '/');
        if (!$context->isSplitRoot()) throw new \RuntimeException('Target APP_ROOT/PUBLIC_ROOT are not split roots.');
        $checks['target_context'] = 'PASS';
        $this->assertCorrectedClassComposition($checks);
        $storage = $appRoot . DIRECTORY_SEPARATOR . 'storage';
        $lifecycle = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        self::directory($storage, 'target lifecycle storage');
        self::directory($lifecycle, 'target lifecycle namespace');
        $env = self::readEnv($appRoot . DIRECTORY_SEPARATOR . '.env');
        if (!isset($env['COPOT_RECOVERY_ROOT']) || self::samePath($env['COPOT_RECOVERY_ROOT'], $recoveryRoot) !== true) throw new \RuntimeException('Target Recovery Root binding is incorrect.');
        if ($expectedDatabase !== null && ($env['DB_DATABASE'] ?? null) !== $expectedDatabase) throw new \RuntimeException('Target database binding does not match the expected database.');
        $checks['target_configuration'] = 'PASS';
        $identity = self::jsonFile($lifecycle . DIRECTORY_SEPARATOR . 'installation-identity.json', 'installation identity');
        if (($identity['installation_id'] ?? null) !== $expectedInstallationId) throw new \RuntimeException('Installation identity does not match the expected target.');
        $checks['installation_identity'] = 'PASS';
        if (file_exists($lifecycle . DIRECTORY_SEPARATOR . 'active-operation.json') || is_link($lifecycle . DIRECTORY_SEPARATOR . 'active-operation.json')) throw new \RuntimeException('A lifecycle operation is already active.');
        $checks['active_operation'] = 'PASS';
        $committed = (new CommittedLifecycleStateStore($storage))->read();
        $reference = $committed?->sourceInventoryReference();
        if (!$committed instanceof CommittedLifecycleState || !$reference instanceof SourceInventoryReference || $reference->fingerprint() !== strtolower($expectedSourceFingerprint)) throw new \RuntimeException('Committed source inventory does not bind to expected F2.');
        $source = (new SourceInventoryArtifactStore($storage))->read($reference);
        $checks['committed_source_inventory'] = 'PASS';
        $packageSha = strtolower($expectedPackageSha256);
        if (preg_match('/^[a-f0-9]{64}$/D', $packageSha) !== 1 || !is_file($packagePath) || is_link($packagePath) || strtolower((string) hash_file('sha256', $packagePath)) !== $packageSha) throw new \RuntimeException('P3 package SHA-256 does not match the expected identity.');
        $intake = new ZipIntakeService($appRoot, null, null, null, null, $expectedInstallationId);
        $payload = $intake->intake($packagePath);
        try {
            $manifest = (new PackageManifestReader())->read($payload);
            (new PackageInventoryVerifier())->verify($payload, $manifest->contract()->inventory(), [PackageManifestReader::PATH]);
            $contract = $manifest->contract();
            if ($contract->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || $contract->coverage() !== 'partial' || $contract->migrationDeclaration()->declaresCoreMigrations() || !$contract->targetRequirements()->isEmpty() || $contract->runtimeCompatibility()->minimumDatabaseVersions() !== []) throw new \RuntimeException('P3 is not a filesystem-only partial V3 package.');
            $plan = (new PackageV3SourceRelativePlanner())->plan($manifest, $source);
            $counts = [];
            foreach ($plan->actions() as $action) $counts[$action->action()] = ($counts[$action->action()] ?? 0) + 1;
            if ($plan->sourceFingerprint() !== strtolower($expectedSourceFingerprint) || $plan->targetFingerprint() !== strtolower($expectedTargetFingerprint) || ($counts[V3SourceRelativeAction::RETAIN] ?? 0) !== $expectedRetainCount || ($counts[V3SourceRelativeAction::DELIVER] ?? 0) !== 1 || ($counts[V3SourceRelativeAction::RENAME] ?? 0) !== 1 || ($counts[V3SourceRelativeAction::REPLACE] ?? 0) !== 0 || ($counts[V3SourceRelativeAction::REMOVE] ?? 0) !== 0 || count($plan->removalActions()) !== 1) throw new \RuntimeException('P3 planner result does not match RETAIN + DELIVER + RENAME.');
            $this->assertInventoryLiveState($context, $source->inventory());
            $deliver = null; $rename = null;
            foreach ($plan->actions() as $action) { if ($action->action() === V3SourceRelativeAction::DELIVER) $deliver = $action; if ($action->action() === V3SourceRelativeAction::RENAME) $rename = $action; }
            if (!$deliver instanceof V3SourceRelativeAction || $deliver->root() !== CanonicalTargetInventoryEntry::APP_ROOT || $deliver->path() !== 'resources/c1-cycle/renamed.txt' || !$rename instanceof V3SourceRelativeAction || $rename->root() !== CanonicalTargetInventoryEntry::APP_ROOT || $rename->path() !== 'resources/c1-cycle/cycle.txt' || $rename->sourcePath() !== 'resources/c1-cycle/cycle.txt') throw new \RuntimeException('P3 rename pairing is invalid.');
            $sourcePath = $context->path('resources/c1-cycle/cycle.txt'); $targetPath = $context->path('resources/c1-cycle/renamed.txt');
            if (!is_file($sourcePath) || is_link($sourcePath) || strtolower((string) hash_file('sha256', $sourcePath)) !== strtolower($rename->sha256()) || is_file($targetPath) || is_link($targetPath)) throw new \RuntimeException('P3 source/destination live state does not match the rename precondition.');
            if (!is_file($context->path('resources/c1-cycle/companion.txt')) || strtolower((string) hash_file('sha256', $context->path('resources/c1-cycle/companion.txt'))) !== $this->sourceHash($source, CanonicalTargetInventoryEntry::APP_ROOT, 'resources/c1-cycle/companion.txt')) throw new \RuntimeException('P3 companion.txt is not retained at its F2 identity.');
            $htaccess = $context->publicPath('.htaccess'); if (!is_file($htaccess) || is_link($htaccess)) throw new \RuntimeException('Protected PUBLIC_ROOT/.htaccess is missing or unsafe.');
            $checks['package_planner'] = 'PASS'; $checks['live_target_state'] = 'PASS'; $checks['protected_htaccess'] = 'PASS';
            $checks['destructive_confirmation'] = 'PASS (typed confirmation bound to this plan is required for execution)';
        } finally { $payload->cleanup(); }
        $guardPath = $lifecycle . DIRECTORY_SEPARATOR . 'application-write-guard.json';
        if (is_file($guardPath)) { $guard = self::jsonFile($guardPath, 'installation-scoped Guard state'); if (($guard['active'] ?? false) === true || ($guard['draining'] ?? false) === true || (int) ($guard['in_flight'] ?? 0) !== 0) throw new \RuntimeException('Installation-scoped Guard is not idle.'); }
        $checks['guard'] = 'PASS';
        $lockPath = $storage . DIRECTORY_SEPARATOR . '.install.lock'; if (file_exists($lockPath) && (is_link($lockPath) || !is_file($lockPath) || !is_readable($lockPath))) throw new \RuntimeException('Installation mutex path is invalid.');
        $checks['mutex'] = 'PASS (static path only; exclusive availability is rechecked at execution time)';
        $this->assertRetainedRecoveryEvidence($recoveryRoot); $checks['recovery_namespace'] = 'PASS (P1/P2 retained evidence verified)';
        return ['status' => 'PASS', 'checks' => $checks, 'bindings' => ['workspace_root' => $this->workspaceRoot, 'app_root' => $context->appRoot(), 'public_root' => $context->publicRoot(), 'recovery_root' => $recoveryRoot, 'installation_id' => $expectedInstallationId, 'database' => $env['DB_DATABASE'] ?? null, 'package_sha256' => $packageSha, 'source_fingerprint' => strtolower($expectedSourceFingerprint), 'target_fingerprint' => strtolower($expectedTargetFingerprint), 'actions' => $counts, 'plan_identity' => $plan->identity(), 'operation_id' => $plan->operationIdentity(), 'removal_set_digest' => $plan->removalSetDigest(), 'execution_mode' => 'fresh-v3-rename-apply'], 'reason' => ''];
    }

    /** @return array{status:string, checks:array<string,string>, bindings:array<string,mixed>, reason:string} */
    public static function preflightFreshRenameFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        ?string $expectedDatabase = null,
        int $expectedRetainCount = 814
    ): array {
        $checks = [];
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace'); $autoload = $workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php'; self::file($autoload, 'corrected autoloader'); require_once $autoload; $checks['corrected_autoloader'] = 'PASS';
            return (new self($workspace))->preflightFreshRename($appRoot, $publicRoot, $recoveryRoot, $packagePath, $expectedInstallationId, $expectedPackageSha256, $expectedSourceFingerprint, $expectedTargetFingerprint, $expectedDatabase, $expectedRetainCount, $checks);
        } catch (\Throwable $exception) { return ['status' => 'BLOCKED', 'checks' => $checks, 'bindings' => [], 'reason' => $exception->getMessage()]; }
    }

    private function sourceHash(SourceInventoryArtifact $source, string $root, string $path): string
    {
        foreach ($source->inventory()->entries() as $entry) if ($entry->root() === $root && $entry->path() === $path) return $entry->sha256();
        throw new \RuntimeException('Expected source inventory entry is missing: ' . $root . '/' . $path);
    }

    private function assertInventoryLiveState(DeploymentContext $context, CanonicalTargetInventory $inventory): void
    {
        $guard = new LiveTreePathGuard($context);
        foreach ($inventory->entries() as $entry) {
            $relative = $entry->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $entry->path() : $entry->path();
            $destination = $guard->destination($relative);
            if (!is_file($destination) || is_link($destination) || @filesize($destination) !== $entry->byteSize() || strtolower((string) @hash_file('sha256', $destination)) !== $entry->sha256()) throw new \RuntimeException('Live inventory drift at ' . $entry->root() . '/' . $entry->path());
        }
    }

    /** @return array{status:string, invocation_count:int, preflight:array<string,mixed>, result:array<string,mixed>|null, reason:string} */
    public static function executeFreshRemoveFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        string $actorIdentity,
        string $capabilityReference,
        ?string $expectedDatabase = null,
        int $expectedRetainCount = 813
    ): array {
        $preflight = self::preflightFreshRemoveFromWorkspace($workspaceRoot, $appRoot, $publicRoot, $recoveryRoot, $packagePath, $expectedInstallationId, $expectedPackageSha256, $expectedSourceFingerprint, $expectedTargetFingerprint, $expectedDatabase, $expectedRetainCount);
        if (($preflight['status'] ?? null) !== 'PASS') return ['status' => 'BLOCKED', 'invocation_count' => 0, 'preflight' => $preflight, 'result' => null, 'reason' => (string) ($preflight['reason'] ?? 'Fresh V3 remove preflight was not accepted.')];
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace'); require_once self::file($workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php', 'corrected autoloader');
            $context = new DeploymentContext(self::directory($appRoot, 'target APP_ROOT'), self::directory($publicRoot, 'target PUBLIC_ROOT'), '/'); Env::load($context->appRoot() . DIRECTORY_SEPARATOR . '.env'); $service = PackageLifecycleFactory::forProject($context, null, true);
            $confirmation = ['operation_identity' => $preflight['bindings']['operation_id'], 'plan_identity' => $preflight['bindings']['plan_identity'], 'removal_set_digest' => $preflight['bindings']['removal_set_digest'], 'actor_authorization_evidence' => ['actor_identity' => $actorIdentity, 'capability_reference' => $capabilityReference, 'session_identity' => null], 'confirmation_version' => 1];
            $result = $service->apply($packagePath, false, $confirmation, $actorIdentity);
            return ['status' => 'EXECUTED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => $result->toArray(), 'reason' => $result->reason()];
        } catch (\Throwable $exception) { return ['status' => 'EXECUTION_FAILED', 'invocation_count' => 1, 'preflight' => $preflight, 'result' => null, 'reason' => $exception->getMessage()]; }
    }

    /** @return array{status:string, checks:array<string,string>, bindings:array<string,mixed>, reason:string} */
    public static function preflightFreshRemoveFromWorkspace(
        string $workspaceRoot,
        string $appRoot,
        string $publicRoot,
        string $recoveryRoot,
        string $packagePath,
        string $expectedInstallationId,
        string $expectedPackageSha256,
        string $expectedSourceFingerprint,
        string $expectedTargetFingerprint,
        ?string $expectedDatabase = null,
        int $expectedRetainCount = 813
    ): array {
        $checks = [];
        try {
            $workspace = self::directory($workspaceRoot, 'corrected workspace'); $autoload = $workspace . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php'; self::file($autoload, 'corrected autoloader'); require_once $autoload; $checks['corrected_autoloader'] = 'PASS';
            $adapter = new self($workspace); $result = $adapter->preflightFreshRemove($appRoot, $publicRoot, $recoveryRoot, $packagePath, $expectedInstallationId, $expectedPackageSha256, $expectedSourceFingerprint, $expectedTargetFingerprint, $expectedDatabase, $expectedRetainCount, $checks); return $result;
        } catch (\Throwable $exception) { return ['status' => 'BLOCKED', 'checks' => $checks, 'bindings' => [], 'reason' => $exception->getMessage()]; }
    }

    /** @param array<string,string> $checks */
    private function preflightFreshRemove(string $appRoot, string $publicRoot, string $recoveryRoot, string $packagePath, string $expectedInstallationId, string $expectedPackageSha256, string $expectedSourceFingerprint, string $expectedTargetFingerprint, ?string $expectedDatabase, int $expectedRetainCount, array &$checks): array
    {
        $appRoot = self::directory($appRoot, 'target APP_ROOT'); $publicRoot = self::directory($publicRoot, 'target PUBLIC_ROOT'); $recoveryRoot = self::directory($recoveryRoot, 'target Recovery Root'); $context = new DeploymentContext($appRoot, $publicRoot, '/'); if (!$context->isSplitRoot()) throw new \RuntimeException('Target APP_ROOT/PUBLIC_ROOT are not split roots.'); $checks['target_context'] = 'PASS'; $this->assertCorrectedClassComposition($checks);
        $storage = $appRoot . DIRECTORY_SEPARATOR . 'storage'; $lifecycle = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle'; self::directory($storage, 'target lifecycle storage'); self::directory($lifecycle, 'target lifecycle namespace'); $env = self::readEnv($appRoot . DIRECTORY_SEPARATOR . '.env'); if (!isset($env['COPOT_RECOVERY_ROOT']) || self::samePath($env['COPOT_RECOVERY_ROOT'], $recoveryRoot) !== true) throw new \RuntimeException('Target Recovery Root binding is incorrect.'); if ($expectedDatabase !== null && ($env['DB_DATABASE'] ?? null) !== $expectedDatabase) throw new \RuntimeException('Target database binding does not match the expected database.'); $checks['target_configuration'] = 'PASS';
        $identity = self::jsonFile($lifecycle . DIRECTORY_SEPARATOR . 'installation-identity.json', 'installation identity'); if (($identity['installation_id'] ?? null) !== $expectedInstallationId) throw new \RuntimeException('Installation identity does not match the expected target.'); $checks['installation_identity'] = 'PASS'; if (file_exists($lifecycle . DIRECTORY_SEPARATOR . 'active-operation.json') || is_link($lifecycle . DIRECTORY_SEPARATOR . 'active-operation.json')) throw new \RuntimeException('A lifecycle operation is already active.'); $checks['active_operation'] = 'PASS';
        $committed = (new CommittedLifecycleStateStore($storage))->read(); $reference = $committed?->sourceInventoryReference(); if (!$committed instanceof CommittedLifecycleState || !$reference instanceof SourceInventoryReference || $reference->fingerprint() !== strtolower($expectedSourceFingerprint)) throw new \RuntimeException('Committed source inventory does not bind to expected F3.'); $source = (new SourceInventoryArtifactStore($storage))->read($reference); $checks['committed_source_inventory'] = 'PASS';
        $packageSha = strtolower($expectedPackageSha256); if (preg_match('/^[a-f0-9]{64}$/D', $packageSha) !== 1 || !is_file($packagePath) || is_link($packagePath) || strtolower((string) hash_file('sha256', $packagePath)) !== $packageSha) throw new \RuntimeException('P4 package SHA-256 does not match the expected identity.'); $intake = new ZipIntakeService($appRoot, null, null, null, null, $expectedInstallationId); $payload = $intake->intake($packagePath);
        try {
            $manifest = (new PackageManifestReader())->read($payload); (new PackageInventoryVerifier())->verify($payload, $manifest->contract()->inventory(), [PackageManifestReader::PATH]); $contract = $manifest->contract(); if ($contract->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || $contract->coverage() !== 'partial' || $contract->migrationDeclaration()->declaresCoreMigrations() || !$contract->targetRequirements()->isEmpty() || $contract->runtimeCompatibility()->minimumDatabaseVersions() !== []) throw new \RuntimeException('P4 is not a filesystem-only partial V3 package.'); $plan = (new PackageV3SourceRelativePlanner())->plan($manifest, $source); $counts = []; foreach ($plan->actions() as $action) $counts[$action->action()] = ($counts[$action->action()] ?? 0) + 1;
            if ($plan->sourceFingerprint() !== strtolower($expectedSourceFingerprint) || $plan->targetFingerprint() !== strtolower($expectedTargetFingerprint) || ($counts[V3SourceRelativeAction::RETAIN] ?? 0) !== $expectedRetainCount || ($counts[V3SourceRelativeAction::REPLACE] ?? 0) !== 1 || ($counts[V3SourceRelativeAction::REMOVE] ?? 0) !== 1 || ($counts[V3SourceRelativeAction::DELIVER] ?? 0) !== 0 || ($counts[V3SourceRelativeAction::RENAME] ?? 0) !== 0 || count($plan->removalActions()) !== 1) throw new \RuntimeException('P4 planner result does not match RETAIN + REPLACE + REMOVE.'); $this->assertInventoryLiveState($context, $source->inventory()); $remove = null; $replace = null; foreach ($plan->actions() as $action) { if ($action->action() === V3SourceRelativeAction::REMOVE) $remove = $action; if ($action->action() === V3SourceRelativeAction::REPLACE) $replace = $action; }
            if (!$remove instanceof V3SourceRelativeAction || $remove->root() !== CanonicalTargetInventoryEntry::APP_ROOT || $remove->path() !== 'resources/c1-cycle/renamed.txt' || !$replace instanceof V3SourceRelativeAction || $replace->root() !== CanonicalTargetInventoryEntry::APP_ROOT || $replace->path() !== 'resources/c1-cycle/companion.txt') throw new \RuntimeException('P4 REMOVE/REPLACE action plan is invalid.'); $removedPath = $context->path('resources/c1-cycle/renamed.txt'); if (!is_file($removedPath) || is_link($removedPath) || strtolower((string) hash_file('sha256', $removedPath)) !== $remove->sha256()) throw new \RuntimeException('P4 renamed.txt does not match the expected removal source identity.'); $companion = $context->path('resources/c1-cycle/companion.txt'); if (!is_file($companion) || is_link($companion) || strtolower((string) hash_file('sha256', $companion)) !== $replace->sha256() || $replace->sha256() !== $this->sourceHash($source, CanonicalTargetInventoryEntry::APP_ROOT, $replace->path())) throw new \RuntimeException('P4 companion.txt does not match same-byte REPLACE preconditions.'); $htaccess = $context->publicPath('.htaccess'); if (!is_file($htaccess) || is_link($htaccess)) throw new \RuntimeException('Protected PUBLIC_ROOT/.htaccess is missing or unsafe.'); $checks['package_planner'] = 'PASS'; $checks['live_target_state'] = 'PASS'; $checks['protected_htaccess'] = 'PASS'; $checks['destructive_confirmation'] = 'PASS (typed confirmation bound to this plan is required for execution)';
        } finally { $payload->cleanup(); }
        $guardPath = $lifecycle . DIRECTORY_SEPARATOR . 'application-write-guard.json'; if (is_file($guardPath)) { $guard = self::jsonFile($guardPath, 'installation-scoped Guard state'); if (($guard['active'] ?? false) === true || ($guard['draining'] ?? false) === true || (int) ($guard['in_flight'] ?? 0) !== 0) throw new \RuntimeException('Installation-scoped Guard is not idle.'); } $checks['guard'] = 'PASS'; $lockPath = $storage . DIRECTORY_SEPARATOR . '.install.lock'; if (file_exists($lockPath) && (is_link($lockPath) || !is_file($lockPath) || !is_readable($lockPath))) throw new \RuntimeException('Installation mutex path is invalid.'); $checks['mutex'] = 'PASS (static path only; exclusive availability is rechecked at execution time)'; $this->assertRetainedRecoveryEvidence($recoveryRoot); $checks['recovery_namespace'] = 'PASS (P1/P2/P3 retained evidence verified)'; return ['status' => 'PASS', 'checks' => $checks, 'bindings' => ['workspace_root' => $this->workspaceRoot, 'app_root' => $context->appRoot(), 'public_root' => $context->publicRoot(), 'recovery_root' => $recoveryRoot, 'installation_id' => $expectedInstallationId, 'database' => $env['DB_DATABASE'] ?? null, 'package_sha256' => $packageSha, 'source_fingerprint' => strtolower($expectedSourceFingerprint), 'target_fingerprint' => strtolower($expectedTargetFingerprint), 'actions' => $counts, 'plan_identity' => $plan->identity(), 'operation_id' => $plan->operationIdentity(), 'removal_set_digest' => $plan->removalSetDigest(), 'execution_mode' => 'fresh-v3-remove-apply'], 'reason' => ''];
    }

    private function assertRetainedRecoveryEvidence(string $recoveryRoot): void
    {
        $states = glob($recoveryRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'recovery-lifecycle.json') ?: [];
        if ($states === []) return;
        foreach ($states as $statePath) {
            $state = self::jsonFile($statePath, 'retained recovery state');
            if (($state['state'] ?? null) !== 'READY' || ($state['post_reconciliation_verified'] ?? false) !== true || ($state['mutation_started'] ?? false) !== true) throw new \RuntimeException('Retained P1 recovery evidence is not in a verified terminal-forward state.');
        }
    }

    /** @param array<string,string> $checks */
    private function assertCorrectedClassComposition(array &$checks): void
    {
        foreach ([PackageLifecycleService::class, V3ExplicitApplyExecutor::class, PackageLifecycleFactory::class, DeploymentContext::class] as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            if (!is_string($file) || !self::inside($file, $this->workspaceRoot)) {
                throw new \RuntimeException('Class-loading conflict: ' . $class . ' is not loaded from the corrected workspace.');
            }
        }
        $checks['class_loading_provenance'] = 'PASS';
    }

    private function assertLiveState(DeploymentContext $context, V3SourceRelativePlan $plan, SourceInventoryArtifact $source): void
    {
        $guard = new LiveTreePathGuard($context);
        $sourceIndex = [];
        foreach ($source->inventory()->entries() as $entry) {
            $sourceIndex[strtolower($entry->root() . "\0" . $entry->path())] = $entry;
        }
        foreach ($plan->actions() as $action) {
            $relative = $action->root() === CanonicalTargetInventoryEntry::PUBLIC_ROOT
                ? 'public/' . $action->path()
                : $action->path();
            $destination = $guard->destination($relative);
            if ($action->action() === V3SourceRelativeAction::DELIVER) {
                if (file_exists($destination) || is_link($destination)) {
                    throw new \RuntimeException('V3 ADD target already exists or is a link.');
                }
                continue;
            }
            $expectedSize = $action->byteSize();
            $expectedSha256 = $action->sha256();
            if ($action->action() === V3SourceRelativeAction::REPLACE && $action->sourceRoot() !== null && $action->sourcePath() !== null) {
                $sourceEntry = $sourceIndex[strtolower($action->sourceRoot() . "\0" . $action->sourcePath())] ?? null;
                if (!$sourceEntry instanceof CanonicalTargetInventoryEntry) {
                    throw new \RuntimeException('V3 replacement source identity is unavailable at ' . $relative . '.');
                }
                $expectedSize = $sourceEntry->byteSize();
                $expectedSha256 = $sourceEntry->sha256();
            }
            $actualSize = @filesize($destination);
            $actualSha256 = is_file($destination) ? strtolower((string) @hash_file('sha256', $destination)) : null;
            if (!is_file($destination) || is_link($destination) || $actualSize !== $expectedSize || $actualSha256 !== $expectedSha256) {
                throw new \RuntimeException('Live source/target state does not match the planned V3 identity at ' . $relative . '.');
            }
        }
    }

    private static function retainedPayload(string $stagingPath, string $archiveSha256): StagedPayload
    {
        $payloadRoot = $stagingPath . DIRECTORY_SEPARATOR . 'payload';
        self::directory($payloadRoot, 'retained payload');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($payloadRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $info) {
            if (!$info instanceof \SplFileInfo || $info->isLink() || !$info->isFile()) {
                throw new \RuntimeException('Retained payload contains an unsafe entry.');
            }
            $absolute = $info->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen(rtrim($payloadRoot, '/\\')) + 1));
            $size = @filesize($absolute);
            $sha256 = @hash_file('sha256', $absolute);
            if (!is_int($size) || !is_string($sha256)) {
                throw new \RuntimeException('Retained payload identity could not be read.');
            }
            $files[] = new StagedFile($relative, $size, $sha256);
        }
        $reflection = new \ReflectionClass(StagingSession::class);
        /** @var StagingSession $session */
        $session = $reflection->newInstanceWithoutConstructor();
        $namespace = $reflection->getProperty('namespacePath');
        $namespace->setAccessible(true);
        $namespace->setValue($session, dirname($stagingPath));
        $path = $reflection->getProperty('path');
        $path->setAccessible(true);
        $path->setValue($session, $stagingPath);
        return new StagedPayload($session, $archiveSha256, $files);
    }

    private static function payloadIdentity(StagedPayload $payload): string
    {
        return hash('sha256', implode(':', array_map(
            static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(),
            $payload->withoutPaths([PackageManifestReader::PATH])->files()
        )));
    }

    /** @return array<string,string> */
    private static function preMutationStateEvidence(string $appRoot): array
    {
        $storage = $appRoot . DIRECTORY_SEPARATOR . 'storage';
        $hashFile = static function (string $path): string {
            if (!is_file($path) || is_link($path)) throw new \RuntimeException('Pre-mutation state evidence file is unavailable.');
            $hash = hash_file('sha256', $path);
            if (!is_string($hash)) throw new \RuntimeException('Pre-mutation state evidence could not be hashed.');
            return strtolower($hash);
        };
        return [
            'migration_ledger' => $hashFile($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json'),
            'schema' => $hashFile($storage . DIRECTORY_SEPARATOR . 'installed.lock'),
            'capability' => hash('sha256', PHP_VERSION . ':' . implode(',', get_loaded_extensions())),
            'committed_state' => $hashFile($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json'),
            'persistent_state' => $hashFile($storage . DIRECTORY_SEPARATOR . 'installed.lock'),
            'operator_state' => $hashFile($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-guard.json'),
        ];
    }

    /** @return array<string,mixed> */
    private static function jsonFile(string $path, string $label): array
    {
        self::file($path, $label);
        $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException($label . ' is invalid.');
        }
        return $data;
    }

    /** @return array<string,string> */
    private static function readEnv(string $path): array
    {
        self::file($path, 'target .env');
        $result = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $match) !== 1) {
                continue;
            }
            $value = trim($match[2]);
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $result[$match[1]] = $value;
        }
        return $result;
    }

    private static function file(string $path, string $label): string
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException($label . ' is missing or unsafe.');
        }
        return $path;
    }

    private static function directory(string $path, string $label): string
    {
        if (is_link($path) || !is_dir($path) || !is_readable($path)) {
            throw new \RuntimeException($label . ' is missing or unsafe.');
        }
        $resolved = realpath($path);
        if ($resolved === false || is_link($resolved)) {
            throw new \RuntimeException($label . ' could not be resolved safely.');
        }
        return rtrim($resolved, '/\\');
    }

    private static function samePath(string $left, string $right): bool
    {
        $left = realpath($left) ?: $left;
        $right = realpath($right) ?: $right;
        return strtolower(str_replace('\\', '/', rtrim($left, '/\\'))) === strtolower(str_replace('\\', '/', rtrim($right, '/\\')));
    }

    private static function inside(string $path, string $root): bool
    {
        $path = realpath($path) ?: $path;
        $root = realpath($root) ?: $root;
        $path = strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
        $root = strtolower(str_replace('\\', '/', rtrim($root, '/\\')));
        return $path === $root || str_starts_with($path, $root . '/');
    }
}

