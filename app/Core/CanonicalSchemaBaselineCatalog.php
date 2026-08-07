<?php

namespace Copot\Core;

use PDO;

final class CanonicalSchemaBaselineCatalog
{
    /** @param list<CanonicalSchemaBaselineDescriptor> $baselines */
    public function __construct(private array $baselines)
    {
        $identities = [];
        foreach ($baselines as $baseline) {
            if (!$baseline instanceof CanonicalSchemaBaselineDescriptor || isset($identities[$baseline->identity()])) {
                throw new \InvalidArgumentException('Canonical schema baseline catalog contains a duplicate or invalid entry.');
            }
            $identities[$baseline->identity()] = true;
        }
    }

    public static function forProject(string $basePath): self
    {
        $currentPath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        $currentHash = is_file($currentPath) ? hash_file('sha256', $currentPath) : false;
        if (!is_string($currentHash)) {
            throw new \RuntimeException('Current canonical schema identity could not be calculated.');
        }

        return new self([
            new CanonicalSchemaBaselineDescriptor('canonical-schema:' . $currentHash, Version::CURRENT, $currentPath, $currentHash, true),
            new CanonicalSchemaBaselineDescriptor(
                'canonical-schema:86431406ec45bcce7f44dd34c4cb146c9b3566868452244db71e0208c007d0f3',
                '0.8.0',
                rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baselines' . DIRECTORY_SEPARATOR . 'webcore-0.8.0.sql',
                '86431406ec45bcce7f44dd34c4cb146c9b3566868452244db71e0208c007d0f3',
                false
            ),
        ]);
    }

    /** @return list<CanonicalSchemaBaselineDescriptor> */
    public function baselines(): array { return $this->baselines; }

    public function verify(PDO $connection, CanonicalSchemaBaselineVerifier $verifier): ?CanonicalSchemaBaselineDescriptor
    {
        foreach ($this->baselines as $baseline) {
            if ($this->fileIdentity($baseline) !== $baseline->schemaFileSha256()) {
                continue;
            }
            if ($verifier->verify($connection, $baseline->schemaPath(), $baseline->migrationLedgerPresent())->passed()) {
                return $baseline;
            }
        }
        return null;
    }

    public function verifyIdentity(PDO $connection, CanonicalSchemaBaselineVerifier $verifier, string $identity): bool
    {
        foreach ($this->baselines as $baseline) {
            if ($baseline->identity() !== $identity) {
                continue;
            }
            return $this->fileIdentity($baseline) === $baseline->schemaFileSha256()
                && $verifier->verify($connection, $baseline->schemaPath(), $baseline->migrationLedgerPresent())->passed();
        }
        return false;
    }

    private function fileIdentity(CanonicalSchemaBaselineDescriptor $baseline): ?string
    {
        if (is_link($baseline->schemaPath()) || !is_file($baseline->schemaPath()) || !is_readable($baseline->schemaPath())) {
            return null;
        }
        $hash = hash_file('sha256', $baseline->schemaPath());
        return is_string($hash) ? $hash : null;
    }
}
