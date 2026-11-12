<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\SourceInventoryReference;

final class ReusableRecoveryPointManifest
{
    public const FORMAT_VERSION = 1;
    public const KIND = 'reusable-filesystem-recovery-point';

    /** @var array<string, RecoveryDomainIdentity> */
    private array $domains;
    /** @var array<string, RecoveryArtifactRecord> */
    private array $artifacts;

    /** @param array<int, RecoveryDomainIdentity> $domains @param array<int, RecoveryArtifactRecord> $artifacts */
    public function __construct(
        private ReusableRecoveryPointIdentity $pointIdentity,
        private string $projectIdentity,
        private string $installationIdentity,
        private string $originatingOperationIdentity,
        private string $creationIdentity,
        private string $createdAt,
        private SourceInventoryReference $sourceInventoryReference,
        private string $capturedLifecycleIdentity,
        private string $capturedInstalledLockIdentity,
        array $domains,
        array $artifacts
    ) {
        self::opaque($projectIdentity, 'Recovery-point project identity');
        if (preg_match('/^inst_[a-f0-9]{32}$/D', $installationIdentity) !== 1) throw new RecoveryInvariantException('Recovery-point installation identity is invalid.');
        self::opaque($originatingOperationIdentity, 'Recovery-point originating operation identity');
        self::opaque($creationIdentity, 'Recovery-point creation identity');
        if (\DateTimeImmutable::createFromFormat(\DATE_ATOM, $createdAt) === false) throw new RecoveryInvariantException('Recovery-point creation timestamp is invalid.');
        self::hash($capturedLifecycleIdentity, 'Captured lifecycle identity');
        self::hash($capturedInstalledLockIdentity, 'Captured installed-lock identity');

        foreach ($domains as $domain) {
            if (!$domain instanceof RecoveryDomainIdentity || isset($this->domains[$domain->identifier()])) throw new RecoveryInvariantException('Recovery-point domain identity is invalid or duplicated.');
            $this->domains[$domain->identifier()] = $domain;
        }
        foreach ($artifacts as $artifact) {
            if (!$artifact instanceof RecoveryArtifactRecord || isset($this->artifacts[$artifact->domainIdentifier()])) throw new RecoveryInvariantException('Recovery-point artifact record is invalid or duplicated.');
            $this->artifacts[$artifact->domainIdentifier()] = $artifact;
        }
        if ($this->domains === [] || count($this->domains) !== count($this->artifacts)) throw new RecoveryInvariantException('Recovery-point domains and artifacts are incomplete.');
        foreach ($this->domains as $identifier => $domain) {
            $artifact = $this->artifacts[$identifier] ?? null;
            if (!$artifact instanceof RecoveryArtifactRecord || $artifact->artifactIdentity() !== $domain->artifactIdentity()) throw new RecoveryInvariantException('Recovery-point domain/artifact binding is invalid.');
        }
        ksort($this->domains, SORT_STRING);
        ksort($this->artifacts, SORT_STRING);
    }

    public function pointIdentity(): ReusableRecoveryPointIdentity { return $this->pointIdentity; }
    public function projectIdentity(): string { return $this->projectIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function originatingOperationIdentity(): string { return $this->originatingOperationIdentity; }
    public function creationIdentity(): string { return $this->creationIdentity; }
    public function createdAt(): string { return $this->createdAt; }
    public function sourceInventoryReference(): SourceInventoryReference { return $this->sourceInventoryReference; }
    public function capturedLifecycleIdentity(): string { return $this->capturedLifecycleIdentity; }
    public function capturedInstalledLockIdentity(): string { return $this->capturedInstalledLockIdentity; }
    /** @return array<int, RecoveryDomainIdentity> */
    public function domainIdentities(): array { return array_values($this->domains); }
    /** @return array<int, RecoveryArtifactRecord> */
    public function artifactRecords(): array { return array_values($this->artifacts); }
    public function artifact(string $domain): ?RecoveryArtifactRecord { return $this->artifacts[$domain] ?? null; }

    public function identity(): string
    {
        $domains = array_map(static fn (RecoveryDomainIdentity $domain): array => ['identifier' => $domain->identifier(), 'ownership_key' => $domain->ownershipKey(), 'scope_identity' => $domain->scopeIdentity(), 'artifact_identity' => $domain->artifactIdentity()], $this->domainIdentities());
        $artifacts = array_map(static fn (RecoveryArtifactRecord $artifact): array => $artifact->toArray(), $this->artifactRecords());
        return hash('sha256', json_encode([
            'format_version' => self::FORMAT_VERSION,
            'kind' => self::KIND,
            'point_identity' => $this->pointIdentity->value(),
            'project_identity' => $this->projectIdentity,
            'installation_identity' => $this->installationIdentity,
            'originating_operation_identity' => $this->originatingOperationIdentity,
            'creation_identity' => $this->creationIdentity,
            'created_at' => $this->createdAt,
            'source_inventory_reference' => $this->sourceInventoryReference->toArray(),
            'captured_lifecycle_identity' => $this->capturedLifecycleIdentity,
            'captured_installed_lock_identity' => $this->capturedInstalledLockIdentity,
            'domain_identities' => $domains,
            'artifacts' => $artifacts,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function opaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new RecoveryInvariantException($label . ' is invalid.');
    }
    private static function hash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($value)) !== 1) throw new RecoveryInvariantException($label . ' is invalid.');
    }
}

