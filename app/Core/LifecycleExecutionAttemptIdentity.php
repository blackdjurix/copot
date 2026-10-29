<?php

namespace Copot\Core;

/** Immutable identity for one execution attempt, never created during planning. */
final class LifecycleExecutionAttemptIdentity
{
    public static function create(): string
    {
        return 'execution-attempt-sha256:' . hash('sha256', random_bytes(32) . microtime(true));
    }

    public static function assert(string $value): void
    {
        if (preg_match('/^execution-attempt-sha256:[a-f0-9]{64}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Execution attempt identity is invalid.');
        }
    }
}
