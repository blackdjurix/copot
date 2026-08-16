<?php

namespace Copot\Core\BackupRecovery;

use PDO;

/** Deployment-gated MariaDB 10.4 read_only profile. It is unavailable unless all evidence is supplied. */
final class MariaDbReadOnlyQuiescence implements DatabaseQuiescenceCapability
{
    private bool $held = false;

    /**
     * @param callable():PDO $reconciliationConnectionFactory
     * @param callable():bool $deploymentEvidence
     */
    public function __construct(
        private PDO $administrativeConnection,
        private $reconciliationConnectionFactory,
        private $deploymentEvidence,
        private ?string $database = null
    ) {
        if (!is_callable($reconciliationConnectionFactory) || !is_callable($deploymentEvidence)) {
            throw new RecoveryLifecycleException('MariaDB quiescence capability configuration is invalid.');
        }
    }

    public function isAvailable(): bool
    {
        if ($this->held || !($this->deploymentEvidence)()) return false;
        try {
            $version = (string)$this->administrativeConnection->query('SELECT VERSION()')->fetchColumn();
            if (!preg_match('/^10\.4(?:\.|$)/', $version)) return false;
            // The deployment evidence callback must prove that ordinary runtime
            // credentials cannot bypass read_only and that the designated
            // reconciliation credential is separately controlled. The
            // administrative connection itself may necessarily have SUPER.
            return true;
        } catch (\Throwable) { return false; }
    }

    public function acquire(): ?DatabaseQuiescenceLease
    {
        if (!$this->isAvailable()) return null;
        try {
            $this->administrativeConnection->exec('SET GLOBAL read_only = ON');
            $value = (int)$this->administrativeConnection->query('SELECT @@GLOBAL.read_only')->fetchColumn();
            if ($value !== 1) { $this->releaseGlobal(); return null; }
            $connection = ($this->reconciliationConnectionFactory)();
            if (!$connection instanceof PDO) { $this->releaseGlobal(); return null; }
            $this->held = true;
            return new MariaDbReadOnlyQuiescenceLease($this, $connection);
        } catch (\Throwable) { $this->releaseGlobal(); return null; }
    }

    public function release(): void
    {
        if (!$this->held) return;
        $this->releaseGlobal();
        $this->held = false;
    }

    private function releaseGlobal(): void
    { try { if ($this->administrativeConnection->exec('SET GLOBAL read_only = OFF') === false) throw new RecoveryLifecycleException('MariaDB read_only release failed.'); $value = (int)$this->administrativeConnection->query('SELECT @@GLOBAL.read_only')->fetchColumn(); if ($value !== 0) throw new RecoveryLifecycleException('MariaDB read_only release was not verified.'); } catch (\Throwable $e) { if ($e instanceof RecoveryLifecycleException) throw $e; throw new RecoveryLifecycleException('MariaDB read_only release failed.', 0, $e); } }
}

final class MariaDbReadOnlyQuiescenceLease implements DatabaseQuiescenceLease
{
    private bool $active = true;
    public function __construct(private MariaDbReadOnlyQuiescence $owner, private PDO $connection) {}
    public function connection(): PDO { return $this->connection; }
    public function isActive(): bool { return $this->active; }
    public function release(): void { if ($this->active) { $this->owner->release(); $this->active = false; } }
    public function __destruct() { $this->release(); }
}
