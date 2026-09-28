<?php

namespace Copot\Core;

final class CoreMigrationRegistry
{
    public const IDENTITY = 'copot-core-current';

    private array $migrations;

    public function __construct(private string $identity, array $migrations)
    {
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException('Core migration registry identity is invalid.');
        }

        $previousSequence = 0;
        $previousTarget = null;
        $ids = [];
        $sequences = [];
        $this->migrations = [];

        foreach ($migrations as $migration) {
            if (!$migration instanceof CoreMigrationDescriptor || isset($ids[$migration->id()]) || isset($sequences[$migration->sequence()])) {
                throw new \InvalidArgumentException('Core migration registry contains a duplicate or invalid descriptor.');
            }

            if ($migration->sequence() <= $previousSequence) {
                throw new \InvalidArgumentException('Core migration registry order is not monotonic.');
            }

            if ($previousTarget !== null && PackageVersion::compare($migration->targetWebcoreVersion(), $previousTarget) < 0) {
                throw new \InvalidArgumentException('Core migration targets are not forward ordered.');
            }

            $ids[$migration->id()] = true;
            $sequences[$migration->sequence()] = true;
            $previousSequence = $migration->sequence();
            $previousTarget = $migration->targetWebcoreVersion();
            $this->migrations[] = $migration;
        }
    }

    public function identity(): string { return $this->identity; }
    public function migrations(): array { return $this->migrations; }

    public static function forProject(string $basePath): self
    {
        $schemaPath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        $schemaIdentity = (new CanonicalSchemaBaselineVerifier())->identity($schemaPath);

        return new self(self::IDENTITY, [
            new CoreMigrationDescriptor(
                'core.security.persistence',
                10,
                Version::CURRENT,
                null,
                Version::CURRENT,
                $schemaIdentity,
                CoreMigrationDescriptor::NON_TRANSACTIONAL,
                'core.security.persistence:v1',
                static function (AuthorizedMigrationContext $context): void {
                    $context->createTable('security_login_attempts', <<<'SQL'
(
    target_hash CHAR(64) NOT NULL PRIMARY KEY,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    locked_until DATETIME NULL,
    last_failure_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX (locked_until, window_started_at)
)
SQL);
                    $context->createTable('security_sessions', <<<'SQL'
(
    session_identity CHAR(64) NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    device_descriptor VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    last_active_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revocation_reason VARCHAR(100) NULL,
    INDEX (user_id, revoked_at, expires_at),
    INDEX (expires_at, revoked_at)
)
SQL);
                    $context->createTable('security_events', <<<'SQL'
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    occurred_at DATETIME NOT NULL,
    category VARCHAR(80) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    target_type VARCHAR(80) NULL,
    target_identity VARCHAR(190) NULL,
    action VARCHAR(100) NOT NULL,
    result VARCHAR(30) NOT NULL,
    context_json TEXT NOT NULL,
    retention_until DATETIME NULL,
    INDEX (occurred_at, id),
    INDEX (retention_until, id)
)
SQL);
                },
                null,
                static fn (AuthorizedMigrationContext $context): bool => $context->tableExists('security_login_attempts')
                    && $context->tableExists('security_sessions')
                    && $context->tableExists('security_events'),
                true,
                new MigrationSchemaSurface(['security_login_attempts', 'security_sessions', 'security_events'])
            ),
        ]);
    }

    public function resolve(PackageMigrationDeclaration $declaration): array
    {
        if (!$declaration->declaresCoreMigrations()) {
            return [];
        }

        if ($declaration->declarationIdentity() !== $this->identity) {
            throw new \RuntimeException('Package migration declaration identity is unknown.');
        }

        return $this->migrations;
    }
}
