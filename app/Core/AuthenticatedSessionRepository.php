<?php

namespace Copot\Core;

use DateTimeImmutable;
use PDO;

final class AuthenticatedSessionRepository
{
    public const IDENTITY_BYTES = 32;
    public const ACTIVITY_WRITE_INTERVAL_SECONDS = 300;

    public function __construct(private Database $database)
    {
    }

    public function create(
        int $userId,
        string $deviceDescriptor,
        DateTimeImmutable $now,
        int $timeoutMinutes
    ): AuthenticatedSessionRecord {
        $identity = bin2hex(random_bytes(self::IDENTITY_BYTES));
        $descriptor = DeviceDescriptor::sanitize($deviceDescriptor);
        $statement = $this->database->connection()->prepare(
            'INSERT INTO ' . $this->database->table('security_sessions') . ' (
                session_identity, user_id, device_descriptor, created_at, last_active_at, expires_at
            ) VALUES (:session_identity, :user_id, :device_descriptor, :created_at, :last_active_at, :expires_at)'
        );
        $timestamp = $now->format('Y-m-d H:i:s');
        $statement->execute([
            'session_identity' => $identity,
            'user_id' => $userId,
            'device_descriptor' => $descriptor,
            'created_at' => $timestamp,
            'last_active_at' => $timestamp,
            'expires_at' => $now->modify('+' . $timeoutMinutes . ' minutes')->format('Y-m-d H:i:s'),
        ]);

        return $this->find($identity) ?? throw new \RuntimeException('Authenticated session was not created.');
    }

    public function find(string $identity): ?AuthenticatedSessionRecord
    {
        if (!self::validIdentity($identity)) {
            return null;
        }

        $statement = $this->database->connection()->prepare(
            'SELECT * FROM ' . $this->database->table('security_sessions') . ' WHERE session_identity = :identity LIMIT 1'
        );
        $statement->execute(['identity' => $identity]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? new AuthenticatedSessionRecord($row) : null;
    }

    /** @return list<AuthenticatedSessionRecord> */
    public function forUser(int $userId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM ' . $this->database->table('security_sessions') . ' WHERE user_id = :user_id ORDER BY created_at DESC, session_identity DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): AuthenticatedSessionRecord => new AuthenticatedSessionRecord($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function touchIfDue(
        string $identity,
        DateTimeImmutable $now,
        int $timeoutMinutes
    ): bool {
        if (!self::validIdentity($identity)) {
            return false;
        }

        $threshold = $now->modify('-' . self::ACTIVITY_WRITE_INTERVAL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
        $statement = $this->database->connection()->prepare(
            'UPDATE ' . $this->database->table('security_sessions') . '
             SET last_active_at = :last_active_at, expires_at = :expires_at
             WHERE session_identity = :identity AND revoked_at IS NULL AND last_active_at <= :threshold'
        );
        $statement->execute([
            'last_active_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $now->modify('+' . $timeoutMinutes . ' minutes')->format('Y-m-d H:i:s'),
            'identity' => $identity,
            'threshold' => $threshold,
        ]);

        return $statement->rowCount() > 0;
    }

    public function revoke(string $identity, string $reason, ?int $userId = null): bool
    {
        if (!self::validIdentity($identity)) {
            return false;
        }

        $sql = 'UPDATE ' . $this->database->table('security_sessions') . '
                SET revoked_at = COALESCE(revoked_at, NOW()), revocation_reason = COALESCE(revocation_reason, :reason)
                WHERE session_identity = :identity AND revoked_at IS NULL';
        $parameters = ['reason' => substr(DeviceDescriptor::sanitize($reason), 0, 100), 'identity' => $identity];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount() > 0;
    }

    public function revokeOthers(int $userId, string $currentIdentity, string $reason = 'signed_out_other_sessions'): int
    {
        if (!self::validIdentity($currentIdentity)) {
            return 0;
        }

        $statement = $this->database->connection()->prepare(
            'UPDATE ' . $this->database->table('security_sessions') . '
             SET revoked_at = NOW(), revocation_reason = :reason
             WHERE user_id = :user_id AND session_identity <> :current_identity AND revoked_at IS NULL'
        );
        $statement->execute([
            'reason' => substr(DeviceDescriptor::sanitize($reason), 0, 100),
            'user_id' => $userId,
            'current_identity' => $currentIdentity,
        ]);

        return $statement->rowCount();
    }

    public static function validIdentity(string $identity): bool
    {
        return preg_match('/\A[0-9a-f]{64}\z/', $identity) === 1;
    }
}
