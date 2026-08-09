<?php

namespace Copot\Core;

final class RuntimeRegistry
{
    private string $path;

    public function __construct(string $storagePath, private InstallationIdentity $installation, private InstallationMutex $mutex)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) {
            throw new \RuntimeException('Runtime Registry storage is unavailable.');
        }
        $root = rtrim(realpath($storagePath) ?: '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Runtime Registry storage could not be created.');
        }
        $this->path = $root . DIRECTORY_SEPARATOR . 'runtime-registry.json';
    }

    public static function runtimeId(): string
    {
        return 'rt_' . bin2hex(random_bytes(16));
    }

    /** @param list<string> $capabilities */
    public function register(string $runtimeId, string $role, array $capabilities, string $webcoreVersion, string $packageIdentity, string $moduleInventoryIdentity, string $deploymentIdentity): RuntimeParticipant
    {
        $this->validateRuntimeId($runtimeId);
        if (trim($role) === '' || trim($webcoreVersion) === '' || trim($packageIdentity) === '' || trim($deploymentIdentity) === '') {
            throw new \InvalidArgumentException('Runtime registration metadata is incomplete.');
        }
        return $this->mutate(function (array &$records) use ($runtimeId, $role, $capabilities, $webcoreVersion, $packageIdentity, $moduleInventoryIdentity, $deploymentIdentity): RuntimeParticipant {
            if (isset($records[$runtimeId]) && $records[$runtimeId]['state'] === RuntimeParticipant::DETACHED) {
                throw new \RuntimeException('Detached runtime identity cannot be silently re-registered.');
            }
            $now = gmdate(DATE_ATOM);
            $records[$runtimeId] = [
                'runtime_id' => $runtimeId,
                'installation_id' => $this->installation->value(),
                'role' => $role,
                'capabilities' => array_values(array_unique(array_map('strval', $capabilities))),
                'webcore_version' => $webcoreVersion,
                'package_identity' => $packageIdentity,
                'module_inventory_identity' => $moduleInventoryIdentity,
                'deployment_identity' => $deploymentIdentity,
                'state' => RuntimeParticipant::REGISTERED,
                'registered_at' => $records[$runtimeId]['registered_at'] ?? $now,
                'last_seen_at' => $now,
                'detached_at' => null,
                'compatibility_reason' => '',
            ];
            return new RuntimeParticipant($records[$runtimeId]);
        });
    }

    public function heartbeat(string $runtimeId): RuntimeParticipant
    {
        return $this->mutate(function (array &$records) use ($runtimeId): RuntimeParticipant {
            $record = $this->record($records, $runtimeId);
            if (in_array($record['state'], [RuntimeParticipant::DETACHED, RuntimeParticipant::INCOMPATIBLE], true)) {
                throw new \RuntimeException('Runtime cannot heartbeat in its current state.');
            }
            $record['state'] = RuntimeParticipant::ACTIVE;
            $record['last_seen_at'] = gmdate(DATE_ATOM);
            $records[$runtimeId] = $record;
            return new RuntimeParticipant($record);
        });
    }

    public function markStale(int $maxAgeSeconds = 300): int
    {
        if ($maxAgeSeconds < 1) throw new \InvalidArgumentException('Runtime staleness threshold is invalid.');
        return $this->mutate(function (array &$records) use ($maxAgeSeconds): int {
            $changed = 0;
            $cutoff = time() - $maxAgeSeconds;
            foreach ($records as &$record) {
                if (in_array($record['state'], [RuntimeParticipant::DETACHED, RuntimeParticipant::INCOMPATIBLE], true)) continue;
                $lastSeen = strtotime((string) $record['last_seen_at']);
                if ($lastSeen !== false && $lastSeen < $cutoff) { $record['state'] = RuntimeParticipant::STALE; $changed++; }
            }
            return $changed;
        });
    }

    public function evaluateCompatibility(string $runtimeId, array $requirements): RuntimeParticipant
    {
        return $this->mutate(function (array &$records) use ($runtimeId, $requirements): RuntimeParticipant {
            $record = $this->record($records, $runtimeId);
            $result = (new RuntimeCompatibilityEvaluator())->evaluate(new RuntimeParticipant($record), $requirements);
            if ($record['state'] !== RuntimeParticipant::DETACHED) {
                $record['state'] = $result['state'];
                $record['compatibility_reason'] = $result['reason'];
                $records[$runtimeId] = $record;
            }
            return new RuntimeParticipant($record);
        });
    }

    public function detach(string $runtimeId): RuntimeParticipant
    {
        return $this->mutate(function (array &$records) use ($runtimeId): RuntimeParticipant {
            $record = $this->record($records, $runtimeId);
            $record['state'] = RuntimeParticipant::DETACHED;
            $record['detached_at'] = gmdate(DATE_ATOM);
            $records[$runtimeId] = $record;
            return new RuntimeParticipant($record);
        });
    }

    /** @return list<RuntimeParticipant> */
    public function all(): array
    {
        $records = $this->readRecords();
        return array_map(static fn (array $record): RuntimeParticipant => new RuntimeParticipant($record), array_values($records));
    }

    public function assertTransitionAllowed(array $requirements = []): void
    {
        foreach ($this->all() as $runtime) {
            if ($runtime->state() !== RuntimeParticipant::DETACHED && $requirements !== []) {
                $result = (new RuntimeCompatibilityEvaluator())->evaluate($runtime, $requirements);
                if ($result['state'] !== RuntimeParticipant::ACTIVE) {
                    throw new \RuntimeException('Shared-state transition blocked by runtime ' . $runtime->runtimeId() . ': ' . $result['reason']);
                }
            }
            if ($runtime->state() !== RuntimeParticipant::DETACHED && $runtime->state() !== RuntimeParticipant::ACTIVE) {
                throw new \RuntimeException('Shared-state transition blocked by runtime ' . $runtime->runtimeId() . ' in state ' . $runtime->state() . '.');
            }
        }
    }

    private function mutate(callable $operation): mixed
    {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) throw new \RuntimeException('Runtime Registry is busy.');
        try {
            $records = $this->readRecords();
            $result = $operation($records);
            $this->writeRecords($records);
            return $result;
        } finally { $lock->release(); }
    }

    private function readRecords(): array
    {
        if (!file_exists($this->path)) return [];
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($data) || ($data['installation_id'] ?? null) !== $this->installation->value() || !is_array($data['runtimes'] ?? null)) throw new \RuntimeException('Runtime Registry record is invalid.');
        foreach ($data['runtimes'] as $runtime) {
            if (!is_array($runtime) || ($runtime['installation_id'] ?? null) !== $this->installation->value()) throw new \RuntimeException('Runtime Registry contains a foreign installation participant.');
        }
        return $data['runtimes'];
    }

    private function writeRecords(array $records): void
    {
        $temporary = $this->path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($temporary, json_encode(['installation_id' => $this->installation->value(), 'runtimes' => $records], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
        @chmod($temporary, 0600);
        if (!@rename($temporary, $this->path)) { @unlink($temporary); throw new \RuntimeException('Runtime Registry record could not be finalized.'); }
    }

    private function record(array $records, string $runtimeId): array
    {
        $this->validateRuntimeId($runtimeId);
        if (!isset($records[$runtimeId]) || !is_array($records[$runtimeId])) throw new \RuntimeException('Runtime participant is not registered.');
        return $records[$runtimeId];
    }

    private function validateRuntimeId(string $runtimeId): void
    {
        if (preg_match('/\Art_[a-f0-9]{32}\z/', $runtimeId) !== 1) throw new \InvalidArgumentException('Runtime identity is invalid.');
    }
}
