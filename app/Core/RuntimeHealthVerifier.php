<?php

namespace Copot\Core;

final class RuntimeHealthVerifier
{
    public function verify(array $checks): HealthGateMatrix
    {
        if ($checks === []) {
            return new HealthGateMatrix([HealthGateResult::fail('runtime', 'No runtime health checks were supplied.')]);
        }
        $gates = [];
        foreach ($checks as $name => $check) {
            if (!is_string($name) || !is_callable($check)) {
                $gates[] = HealthGateResult::fail((string) $name, 'Runtime health check is invalid.');
                continue;
            }
            try {
                $result = $check();
                $gates[] = $result === true
                    ? HealthGateResult::pass($name)
                    : HealthGateResult::fail($name, is_string($result) ? $result : 'Runtime health check failed.');
            } catch (\Throwable $exception) {
                $gates[] = HealthGateResult::fail($name, $exception->getMessage());
            }
        }
        return new HealthGateMatrix($gates);
    }
}
