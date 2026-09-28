<?php

namespace Copot\Core;

use DateTimeImmutable;
use DateTimeZone;

final class FailedLoginAttemptRepository
{
    private const TABLE = 'security_login_attempts';

    public function __construct(private Database $database)
    {
    }

    public function active(string $targetHash, DateTimeImmutable $now, int $windowSeconds): ?FailedLoginAttempt
    {
        $statement = $this->database->connection()->prepare(
            'SELECT failure_count, window_started_at, locked_until FROM ' . $this->database->table(self::TABLE) . ' WHERE target_hash = :target_hash LIMIT 1'
        );
        $statement->execute(['target_hash' => $targetHash]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        $windowStartedAt = $this->timestamp((string) $row['window_started_at']);
        $lockedUntil = $row['locked_until'] === null ? null : $this->timestamp((string) $row['locked_until']);
        if ($windowStartedAt->modify('+' . $windowSeconds . ' seconds') <= $now || ($lockedUntil !== null && $lockedUntil <= $now)) {
            $this->clear($targetHash);
            return null;
        }

        return new FailedLoginAttempt((int) $row['failure_count'], $windowStartedAt, $lockedUntil);
    }

    public function save(string $targetHash, int $failureCount, DateTimeImmutable $windowStartedAt, DateTimeImmutable $lastFailureAt, ?DateTimeImmutable $lockedUntil): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO ' . $this->database->table(self::TABLE) . ' (target_hash, failure_count, window_started_at, locked_until, last_failure_at, created_at, updated_at) VALUES (:target_hash, :failure_count, :window_started_at, :locked_until, :last_failure_at, :created_at, :updated_at) ON DUPLICATE KEY UPDATE failure_count = VALUES(failure_count), window_started_at = VALUES(window_started_at), locked_until = VALUES(locked_until), last_failure_at = VALUES(last_failure_at), updated_at = VALUES(updated_at)'
        );
        $timestamp = $lastFailureAt->format('Y-m-d H:i:s');
        $statement->execute([
            'target_hash' => $targetHash,
            'failure_count' => $failureCount,
            'window_started_at' => $windowStartedAt->format('Y-m-d H:i:s'),
            'locked_until' => $lockedUntil?->format('Y-m-d H:i:s'),
            'last_failure_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function clear(string $targetHash): void
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM ' . $this->database->table(self::TABLE) . ' WHERE target_hash = :target_hash'
        );
        $statement->execute(['target_hash' => $targetHash]);
    }

    private function timestamp(string $value): DateTimeImmutable
    {
        $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (!$timestamp instanceof DateTimeImmutable) {
            throw new \RuntimeException('Failed-login timestamp is invalid.');
        }

        return $timestamp;
    }
}
