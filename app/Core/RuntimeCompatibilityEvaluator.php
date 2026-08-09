<?php

namespace Copot\Core;

final class RuntimeCompatibilityEvaluator
{
    public function evaluate(RuntimeParticipant $runtime, array $requirements): array
    {
        $data = $runtime->toArray();
        $requiredCapabilities = array_values(array_unique(array_map('strval', $requirements['capabilities'] ?? [])));
        $capabilities = array_fill_keys(array_map('strval', $data['capabilities'] ?? []), true);
        foreach ($requiredCapabilities as $capability) {
            if (!isset($capabilities[$capability])) {
                return ['state' => RuntimeParticipant::INCOMPATIBLE, 'reason' => 'Required runtime capability is unavailable.'];
            }
        }
        foreach (['package_identity', 'deployment_identity'] as $field) {
            if (isset($requirements[$field]) && (string) ($data[$field] ?? '') !== (string) $requirements[$field]) {
                return ['state' => RuntimeParticipant::INCOMPATIBLE, 'reason' => 'Runtime ' . $field . ' is incompatible.'];
            }
        }
        if (isset($requirements['webcore_version']) && (string) ($data['webcore_version'] ?? '') !== (string) $requirements['webcore_version']) {
            return ['state' => RuntimeParticipant::INCOMPATIBLE, 'reason' => 'Runtime Webcore version is incompatible.'];
        }

        return ['state' => RuntimeParticipant::ACTIVE, 'reason' => ''];
    }
}
