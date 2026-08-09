<?php

namespace Copot\Core;

use DateTimeImmutable;

final class ModuleMigrationLedger
{
    private string $root;

    public function __construct(string $storagePath, ?DatabaseTableNames $tables = null)
    {
        if (!is_dir($storagePath) || is_link($storagePath) || !is_writable($storagePath)) throw new \RuntimeException('Module migration storage is unavailable.');
        $base = realpath($storagePath);
        if ($base === false) throw new \RuntimeException('Module migration storage is unavailable.');
        $root = $base . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-migrations';
        if ($tables !== null && $tables->namespace() !== '') {
            $root .= DIRECTORY_SEPARATOR . $tables->namespace();
        }
        if (!is_dir($root) && !mkdir($root, 0700, true)) throw new \RuntimeException('Module migration storage could not be created.');
        if (is_link($root) || !is_dir($root) || !is_writable($root)) throw new \RuntimeException('Module migration storage is invalid.');
        $this->root = realpath($root) ?: throw new \RuntimeException('Module migration storage is invalid.');
    }

    public function read(ModuleIdentity|string $module): array
    {
        $identity = $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module);
        $path = $this->path($identity);
        if (!file_exists($path)) return ['module' => $identity->value(), 'baseline_identity' => null, 'records' => []];
        if (is_link($path) || !is_file($path) || !is_readable($path)) throw new \RuntimeException('Module migration ledger is invalid.');
        try {
            $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            if (($data['module'] ?? null) !== $identity->value() || !is_array($data['records'] ?? null)) throw new \InvalidArgumentException('Invalid ledger payload.');
            $records = [];
            $ids = [];
            $sequences = [];
            foreach ($data['records'] as $row) {
                $record = new ModuleMigrationLedgerRecord($identity, (string) $row['migration_id'], (int) $row['sequence'], (string) $row['target_package_version'], (string) $row['target_schema_identity'], (string) $row['checksum'], (string) $row['executable_identity'], new DateTimeImmutable((string) $row['applied_at']));
                if (isset($ids[$record->migrationId()]) || isset($sequences[$record->sequence()])) throw new \InvalidArgumentException('Duplicate Module migration ledger entry.');
                $ids[$record->migrationId()] = true; $sequences[$record->sequence()] = true; $records[] = $record;
            }
            usort($records, static fn (ModuleMigrationLedgerRecord $a, ModuleMigrationLedgerRecord $b): int => $a->sequence() <=> $b->sequence());
            return ['module' => $identity->value(), 'baseline_identity' => $data['baseline_identity'] ?? null, 'records' => $records];
        } catch (\Throwable $exception) { throw new \RuntimeException('Module migration ledger is invalid.', 0, $exception); }
    }

    public function initializeBaseline(ModuleIdentity $module, string $baselineIdentity): void
    {
        if ($baselineIdentity === '' || trim($baselineIdentity) !== $baselineIdentity) throw new \InvalidArgumentException('Module baseline identity is invalid.');
        $current = $this->read($module);
        if ($current['baseline_identity'] !== null && $current['baseline_identity'] !== $baselineIdentity) throw new \RuntimeException('Module baseline identity cannot be rebound.');
        $this->write($module, $baselineIdentity, $current['records']);
    }

    public function record(ModuleMigrationDescriptor $migration, ModuleIdentity $owner, ?DateTimeImmutable $at = null): void
    {
        $current = $this->read($owner);
        if ($current['baseline_identity'] === null) throw new \RuntimeException('Module migration baseline is not established.');
        foreach ($current['records'] as $record) if ($record->migrationId() === $migration->id()) throw new \RuntimeException('Module migration is already recorded.');
        $current['records'][] = new ModuleMigrationLedgerRecord($owner, $migration->id(), $migration->sequence(), $migration->targetPackageVersion(), $migration->targetSchemaIdentity(), $migration->checksum(), $migration->executableSource(), $at ?? new DateTimeImmutable('now'));
        usort($current['records'], static fn (ModuleMigrationLedgerRecord $a, ModuleMigrationLedgerRecord $b): int => $a->sequence() <=> $b->sequence());
        $this->write($owner, $current['baseline_identity'], $current['records']);
    }

    public function stateIdentity(ModuleIdentity|string $module): string
    {
        $data = $this->read($module); $identity = $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module);
        if ($data['baseline_identity'] === null) return ModuleMigrationStateIdentity::from($identity->value(), 'uninitialized', []);
        return ModuleMigrationStateIdentity::from($identity->value(), $data['baseline_identity'], $data['records']);
    }

    private function write(ModuleIdentity $module, string $baseline, array $records): void
    {
        $payload = ['module' => $module->value(), 'baseline_identity' => $baseline, 'records' => array_map(static fn (ModuleMigrationLedgerRecord $r): array => $r->toArray(), $records)];
        $path = $this->path($module); $tmp = $this->root . DIRECTORY_SEPARATOR . '.' . $module->value() . '-' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = @fopen($tmp, 'xb'); if (!is_resource($handle)) throw new \RuntimeException('Module migration ledger could not be prepared.');
        try { $contents = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL; if (@fwrite($handle, $contents) !== strlen($contents) || !@fflush($handle)) throw new \RuntimeException('Module migration ledger could not be finalized.'); } finally { fclose($handle); }
        @chmod($tmp, 0600); if (!@rename($tmp, $path)) { @unlink($tmp); throw new \RuntimeException('Module migration ledger could not be activated.'); }
    }

    private function path(ModuleIdentity $module): string { return $this->root . DIRECTORY_SEPARATOR . $module->value() . '.json'; }
}
