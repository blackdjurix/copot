<?php

namespace Copot\Core;

/** Typed, provisional handoff evidence. It is never a committed source reference. */
final class LegacyBootstrapSourceArtifact
{
    private function __construct(private array $record, private string $path, private LegacyBootstrapSourceInventory $sourceInventory) {}

    /** @param array<string,mixed> $record */
    public static function fromRecord(array $record, string $path): self
    {
        $entries = [];
        foreach ($record['source_inventory']['entries'] as $entry) {
            $entries[] = new LegacyBootstrapSourceEntry($entry['root'], $entry['path'], $entry['byte_size'], $entry['sha256'], $entry['provenance'], $entry['ownership_evidence']);
        }
        return new self($record, $path, new LegacyBootstrapSourceInventory($entries));
    }

    public function artifactPath(): string { return $this->path; }
    public function operationIdentity(): string { return $this->record['operation_identity']; }
    public function installationIdentity(): string { return $this->record['installation_identity']; }
    public function planIdentity(): string { return $this->record['plan_identity']; }
    public function packageIdentity(): string { return $this->record['package_identity']; }
    public function targetInventoryFingerprint(): string { return $this->record['target_inventory_fingerprint']; }
    public function archiveIdentity(): string { return $this->record['archive_identity']; }
    public function payloadIdentity(): string { return $this->record['payload_identity']; }
    public function applyPlanIdentity(): string { return $this->record['apply_plan_identity']; }
    public function recoveryIdentity(): ?string { return $this->record['recovery_identity']; }
    public function classification(): LegacyClassificationResult { return LegacyClassificationResult::fromEvidenceArray($this->record['legacy_classification']); }
    public function migrationRegistryIdentity(): string { return $this->record['legacy_classification']['migration_registry_identity']; }
    public function installedStateStatus(): string { return $this->record['legacy_classification']['installed_state_status']; }
    public function classificationEvidence(): array { return $this->record['legacy_classification']; }
    public function sourceInventory(): LegacyBootstrapSourceInventory { return $this->sourceInventory; }
    public function sourceInventoryFingerprint(): string { return $this->sourceInventory->fingerprint(); }
    public function collisions(): array { return $this->record['collisions']; }
    public function status(): string { return $this->record['status']; }
    public function provisional(): bool { return true; }
    public function operationalReady(): bool { return false; }
    public function targetFreshnessVerified(): bool { return false; }
    public function toArray(): array { return $this->record + ['artifact_path' => $this->path, 'provisional_source_evidence' => true, 'operational_ready' => false, 'target_freshness_verified' => false]; }
}
