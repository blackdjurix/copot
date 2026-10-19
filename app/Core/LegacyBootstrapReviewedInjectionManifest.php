<?php

namespace Copot\Core;

/** Integrity evidence for a reviewed, external injection manifest. */
final class LegacyBootstrapReviewedInjectionManifest
{
    private function __construct(private string $path, private string $identity, private array $bindings) {}

    public static function fromFile(string $path, string $expectedIdentity): self
    {
        $expectedIdentity = self::validateIdentity($expectedIdentity);
        $path = trim($path);
        $resolved = realpath($path);
        if ($path === '' || $resolved === false || is_link($path) || !is_file($resolved) || !is_readable($resolved)) {
            throw new \InvalidArgumentException('Reviewed injection manifest is missing or unreadable.');
        }

        $contents = file_get_contents($resolved);
        if (!is_string($contents)) {
            throw new \InvalidArgumentException('Reviewed injection manifest could not be read.');
        }
        $bindings = json_decode($contents, true);
        if (!is_array($bindings)
            || !is_string($bindings['installation_identity'] ?? null)
            || !is_string($bindings['operation_identity'] ?? null)
            || !is_string($bindings['plan_identity'] ?? null)
            || !is_string($bindings['original_source_inventory_fingerprint'] ?? null)
            || !is_string($bindings['target_inventory_fingerprint'] ?? null)
            || !is_string($bindings['target_app_root'] ?? null)
            || !is_string($bindings['target_public_root'] ?? null)
            || !is_string($bindings['execution_source_root'] ?? null)
            || !is_array($bindings['changes'] ?? null)) {
            throw new \InvalidArgumentException('Reviewed injection manifest has no complete target binding.');
        }

        $seen = [];
        foreach ($bindings['changes'] as $change) {
            if (!is_array($change)
                || !is_string($change['action'] ?? null)
                || !in_array($change['action'], ['ADD', 'REPLACE', 'REMOVE'], true)
                || !is_string($change['root'] ?? null)
                || !is_string($change['path'] ?? null)
                || !array_key_exists('byte_size', $change)
                || !is_int($change['byte_size'])
                || $change['byte_size'] < 0
                || !is_string($change['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $change['sha256']) !== 1) {
                throw new \InvalidArgumentException('Reviewed injection manifest change is invalid.');
            }
            if (!in_array($change['root'], [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true)) {
                throw new \InvalidArgumentException('Reviewed injection manifest change root is invalid.');
            }
            CanonicalTargetInventoryEntry::assertPath($change['path']);
            $key = strtolower($change['root'] . "\0" . $change['path']);
            if (isset($seen[$key])) throw new \InvalidArgumentException('Reviewed injection manifest contains duplicate changes.');
            $seen[$key] = true;
        }

        $actualIdentity = hash_file('sha256', $resolved);
        if (!is_string($actualIdentity) || !hash_equals($expectedIdentity, strtolower($actualIdentity))) {
            throw new \InvalidArgumentException('Reviewed injection manifest SHA-256 does not match the supplied identity.');
        }

        return new self($resolved, $expectedIdentity, $bindings);
    }

    public static function validateIdentity(string $identity): string
    {
        $identity = strtolower(trim($identity));
        if (!preg_match('/^[a-f0-9]{64}$/D', $identity)) {
            throw new \InvalidArgumentException('Reviewed injection manifest identity must be a SHA-256 identity.');
        }
        return $identity;
    }

    public function path(): string { return $this->path; }
    public function identity(): string { return $this->identity; }
    public function changes(): array { return $this->bindings['changes']; }

    public function assertTargetBoundTo(string $installationIdentity, DeploymentContext $target, string $sourceRoot): void
    {
        if ($this->bindings['installation_identity'] !== $installationIdentity
            || !self::samePath($this->bindings['target_app_root'], $target->appRoot())
            || !self::samePath($this->bindings['target_public_root'], $target->publicRoot())
            || !self::samePath($this->bindings['execution_source_root'], $sourceRoot)) {
            throw new \InvalidArgumentException('Reviewed injection manifest is bound to a different installation or execution source.');
        }
    }

    public function assertBoundTo(string $installationIdentity, DeploymentContext $target, string $sourceRoot, string $operationIdentity, string $planIdentity, string $sourceInventoryFingerprint): void
    {
        $this->assertTargetBoundTo($installationIdentity, $target, $sourceRoot);
        if ($this->bindings['operation_identity'] !== $operationIdentity
            || $this->bindings['plan_identity'] !== $planIdentity
            || $this->bindings['original_source_inventory_fingerprint'] !== $sourceInventoryFingerprint) {
            throw new \InvalidArgumentException('Reviewed injection manifest is bound to a different bootstrap operation or source evidence.');
        }
    }

    public function targetInventoryFingerprint(): string { return $this->bindings['target_inventory_fingerprint']; }

    private static function samePath(string $left, string $right): bool
    {
        $normalize = static fn (string $path): string => strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
        return $normalize($left) === $normalize($right);
    }
}
