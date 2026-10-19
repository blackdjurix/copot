<?php

namespace Copot\Core;

/**
 * Separate-source adapter for preparation-only legacy bootstrap evidence.
 *
 * The execution source is intentionally distinct from the target deployment
 * context. Preparation still delegates to the canonical composer and entry;
 * this class does not create a second preparation or lifecycle authority.
 */
final class LegacyBootstrapPreparationRunner
{
    public function __construct(
        private string $sourceRoot,
        private DeploymentContext $target,
        private LegacyToV3BootstrapPreparationComposer $composer
    ) {
        $this->sourceRoot = self::validateSourceRoot($sourceRoot);
        self::assertDisjoint($this->sourceRoot, $target->appRoot(), $target->publicRoot());
    }

    public static function forSourceAndTarget(
        string $sourceRoot,
        string $targetAppRoot,
        string $targetPublicRoot,
        string $basePath = '/'
    ): self {
        $target = new DeploymentContext($targetAppRoot, $targetPublicRoot, $basePath);

        return new self(
            $sourceRoot,
            $target,
            LegacyToV3BootstrapPreparationComposer::forProject($target)
        );
    }

    public function prepare(string $zip): LegacyBootstrapPreparation
    {
        return $this->composer->prepare($zip);
    }

    public function prepareHandoff(string $zip, string $reviewedInjectionManifestPath, string $reviewedInjectionManifestIdentity): LegacyBootstrapPreparationHandoff
    {
        $reviewedInjectionManifest = LegacyBootstrapReviewedInjectionManifest::fromFile($reviewedInjectionManifestPath, $reviewedInjectionManifestIdentity);
        $targetIdentity = (new InstallationIdentityStore($this->target->appRoot() . DIRECTORY_SEPARATOR . 'storage'))->read();
        if (!$targetIdentity instanceof InstallationIdentity) {
            throw new \InvalidArgumentException('Target installation identity is unavailable for manifest binding.');
        }
        $reviewedInjectionManifest->assertTargetBoundTo($targetIdentity->value(), $this->target, $this->sourceRoot);
        $preparation = $this->prepare($zip);
        if ($preparation->installationIdentity() !== $targetIdentity->value()) throw new \RuntimeException('Preparation installation identity changed during manifest binding.');
        $reviewedInjectionManifest->assertBoundTo($preparation->installationIdentity(), $this->target, $this->sourceRoot, $preparation->operationIdentity(), $preparation->planIdentity(), $preparation->sourceInventory()?->fingerprint() ?? '');
        return new LegacyBootstrapPreparationHandoff(
            $preparation,
            $this->sourceRoot,
            $this->target,
            $reviewedInjectionManifest
        );
    }

    public function sourceRoot(): string
    {
        return $this->sourceRoot;
    }

    public function target(): DeploymentContext
    {
        return $this->target;
    }

    private static function validateSourceRoot(string $sourceRoot): string
    {
        $sourceRoot = trim($sourceRoot);
        $resolved = realpath($sourceRoot);
        if ($sourceRoot === '' || $resolved === false || is_link($sourceRoot) || !is_dir($resolved) || !is_readable($resolved)) {
            throw new \InvalidArgumentException('Bootstrap execution source root is invalid.');
        }
        if (!is_file($resolved . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            throw new \InvalidArgumentException('Bootstrap execution source root has no canonical autoload entrypoint.');
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    private static function assertDisjoint(string $sourceRoot, string $appRoot, string $publicRoot): void
    {
        foreach ([$appRoot, $publicRoot] as $targetRoot) {
            if (self::sameOrWithin($sourceRoot, $targetRoot) || self::sameOrWithin($targetRoot, $sourceRoot)) {
                throw new \InvalidArgumentException('Bootstrap execution source and target roots must be disjoint.');
            }
        }
    }

    private static function sameOrWithin(string $path, string $root): bool
    {
        $path = strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
        $root = strtolower(str_replace('\\', '/', rtrim($root, '/\\')));

        return $path === $root || str_starts_with($path . '/', $root . '/');
    }
}
