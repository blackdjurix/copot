<?php

namespace Copot\Core;

final class TrustedWebcorePackageTarget
{
    private string $packageIdentity;
    private string $inventoryIdentity;

    private function __construct(
        private PackageContract $contract,
        private string $archiveIdentity,
        private string $payloadIdentity
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $archiveIdentity) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $payloadIdentity) !== 1) {
            throw new \InvalidArgumentException('Trusted package artifact identity is invalid.');
        }

        $this->inventoryIdentity = $contract->integrityIdentity();
        $this->packageIdentity = self::hash([
            'package_type' => $contract->packageType(),
            'manifest_contract_version' => $contract->manifestContractVersion(),
            'target_webcore_version' => $contract->targetWebcoreVersion(),
            'release_identity' => $contract->releaseIdentity(),
            'source_tree_identity' => $contract->sourceTreeIdentity(),
            'inventory_identity' => $this->inventoryIdentity,
            'migration_declaration' => $contract->migrationDeclaration()->toArray(),
        ]);
    }

    public static function fromManifest(PackageManifest $manifest, PackageInventoryVerifier $inventoryVerifier): self
    {
        $inventoryVerifier->verify($manifest->payload(), $manifest->contract()->inventory());

        $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());

        return new self(
            $manifest->contract(),
            $manifest->payload()->archiveSha256(),
            $applyPlan->identity()
        );
    }

    public function contract(): PackageContract { return $this->contract; }
    public function archiveIdentity(): string { return $this->archiveIdentity; }
    public function payloadIdentity(): string { return $this->payloadIdentity; }
    public function packageIdentity(): string { return $this->packageIdentity; }
    public function inventoryIdentity(): string { return $this->inventoryIdentity; }

    public function toArray(): array
    {
        return [
            'package_identity' => $this->packageIdentity,
            'package_type' => $this->contract->packageType(),
            'manifest_contract_version' => $this->contract->manifestContractVersion(),
            'target_webcore_version' => $this->contract->targetWebcoreVersion(),
            'release_identity' => $this->contract->releaseIdentity(),
            'archive_identity' => $this->archiveIdentity,
            'payload_identity' => $this->payloadIdentity,
            'inventory_identity' => $this->inventoryIdentity,
            'migration_declaration' => $this->contract->migrationDeclaration()->toArray(),
        ];
    }

    private static function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
