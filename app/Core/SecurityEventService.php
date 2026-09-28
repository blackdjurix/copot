<?php

namespace Copot\Core;

use DateTimeImmutable;
use DateTimeZone;

final class SecurityEventService
{
    public const RETENTION_SECONDS = 90 * 86400;
    public const PRUNE_EVERY_APPENDS = 100;

    private int $appendCount = 0;
    private ?string $lastFailure = null;

    public function __construct(
        private SecurityEventRepository $events,
        private $clock = null
    ) {
    }

    public function record(
        string $category,
        string $severity,
        ?int $actorUserId,
        ?string $targetType,
        ?string $targetIdentity,
        string $action,
        string $result,
        array $context = [],
        ?DateTimeImmutable $now = null
    ): bool {
        $now ??= $this->now();
        $event = [
            'occurred_at' => $now->format('Y-m-d H:i:s'),
            'category' => $this->bounded($category, 80),
            'severity' => $this->bounded($severity, 20),
            'actor_user_id' => $actorUserId,
            'target_type' => $targetType === null ? null : $this->bounded($targetType, 80),
            'target_identity' => $targetIdentity === null ? null : $this->bounded($targetIdentity, 190),
            'action' => $this->bounded($action, 100),
            'result' => $this->bounded($result, 30),
            'context_json' => $this->contextJson($context),
            'retention_until' => $now->modify('+' . self::RETENTION_SECONDS . ' seconds')->format('Y-m-d H:i:s'),
        ];

        try {
            $persisted = $this->events->append($event);
            if ($persisted) {
                $this->appendCount++;
                if ($this->appendCount % self::PRUNE_EVERY_APPENDS === 0) {
                    $this->prune($now);
                }
            }

            return $persisted;
        } catch (\Throwable) {
            $this->lastFailure = 'persistence_unavailable';

            return false;
        }
    }

    public function recordLoginSuccess(int $userId, string $targetHash, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('authentication', 'info', $userId, 'login_target', $targetHash, 'login', 'success', [], $now);
    }

    public function recordLoginFailure(string $targetHash, int $attemptCount, bool $locked, ?DateTimeImmutable $now = null): bool
    {
        return $this->record(
            'authentication',
            $locked ? 'warning' : 'info',
            null,
            'login_target',
            $targetHash,
            'login',
            $locked ? 'locked' : 'failure',
            ['attempt_count' => $attemptCount],
            $now
        );
    }

    public function recordSessionCreated(int $userId, string $sessionIdentity, string $deviceDescriptor, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('session', 'info', $userId, 'session', self::identityEvidence($sessionIdentity), 'session_created', 'success', ['device_descriptor' => $deviceDescriptor], $now);
    }

    public function recordLogout(int $userId, string $sessionIdentity, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('session', 'info', $userId, 'session', self::identityEvidence($sessionIdentity), 'logout', 'revoked', [], $now);
    }

    public function recordSessionRevoked(int $userId, string $sessionIdentity, string $reason, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('session', 'info', $userId, 'session', self::identityEvidence($sessionIdentity), 'session_revoked', 'revoked', ['revocation_reason' => $reason], $now);
    }

    public function recordSignOutOthers(int $userId, string $sessionIdentity, int $revokedCount, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('session', 'info', $userId, 'session', self::identityEvidence($sessionIdentity), 'sign_out_other_sessions', 'revoked', ['revoked_count' => $revokedCount], $now);
    }

    public function recordIdleExpiry(?int $userId, ?string $sessionIdentity, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('session', 'info', $userId, 'session', $sessionIdentity === null ? null : self::identityEvidence($sessionIdentity), 'idle_expiry', 'revoked', [], $now);
    }

    public function recordInvalidSession(?int $userId, ?string $sessionIdentity, string $reason, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('session', 'warning', $userId, 'session', $sessionIdentity === null ? null : self::identityEvidence($sessionIdentity), 'session_validation', 'invalid', ['revocation_reason' => $reason], $now);
    }

    public function recordReauthentication(ReauthenticationResult $result, ?int $userId, ?string $sessionIdentity, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('reauthentication', $result->succeeded() ? 'info' : 'warning', $userId, 'session', $sessionIdentity === null ? null : self::identityEvidence($sessionIdentity), 'reauthenticate', $result->outcome(), [], $now);
    }

    public function recordSecurityPolicyChange(int $actorUserId, string $policyKey, ?DateTimeImmutable $now = null): bool
    {
        return $this->record('security_policy', 'info', $actorUserId, 'policy', $policyKey, 'policy_change', 'success', [], $now);
    }

    /** @return list<array<string,mixed>> */
    public function listRecent(int $limit = 100): array
    {
        return $this->events->list($limit);
    }

    public function prune(?DateTimeImmutable $now = null): int
    {
        $now ??= $this->now();

        try {
            $deleted = $this->events->prune($now->modify('-' . self::RETENTION_SECONDS . ' seconds'));
            $this->lastFailure = null;

            return $deleted;
        } catch (\Throwable) {
            $this->lastFailure = 'persistence_unavailable';

            return 0;
        }
    }

    public function lastFailure(): ?string
    {
        return $this->lastFailure;
    }

    public static function identityEvidence(string $identity): string
    {
        return preg_match('/\A[0-9a-f]{12,64}\z/', $identity) === 1
            ? substr($identity, 0, 12)
            : hash('sha256', 'copot-session-evidence-v1:' . $identity);
    }

    private function contextJson(array $context): string
    {
        $allowed = ['attempt_count', 'device_descriptor', 'revocation_reason', 'revoked_count'];
        $safe = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                continue;
            }
            if (is_int($value) || is_bool($value)) {
                $safe[$key] = $value;
            } elseif (is_string($value)) {
                $safe[$key] = $this->bounded($value, $key === 'device_descriptor' ? 255 : 100);
            }
        }

        return json_encode($safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function bounded(string $value, int $maximum): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return substr($value, 0, $maximum);
    }

    private function now(): DateTimeImmutable
    {
        $value = $this->clock instanceof \Closure ? ($this->clock)() : null;
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if (is_int($value)) {
            return (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
