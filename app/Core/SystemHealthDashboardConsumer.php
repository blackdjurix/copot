<?php

namespace Copot\Core;

final class SystemHealthDashboardConsumer
{
    public function content(?SystemHealthReport $report): array
    {
        if (!$report instanceof SystemHealthReport) {
            return [
                'available' => false,
                'status' => 'unavailable',
                'status_label' => 'Health data unavailable',
                'message' => 'System Health data is not available for this view.',
                'findings' => [],
                'producers' => [],
            ];
        }

        $payload = $report->toArray();
        $status = (string) ($payload['status'] ?? SystemHealthOverallStatus::ATTENTION_REQUIRED);
        $statusLabels = [
            SystemHealthOverallStatus::OPERATIONAL => 'Operational',
            SystemHealthOverallStatus::ATTENTION_REQUIRED => 'Attention required',
            SystemHealthOverallStatus::DEGRADED => 'Degraded',
            SystemHealthOverallStatus::CRITICAL => 'Critical',
        ];

        $findings = [];
        foreach (array_slice(is_array($payload['findings'] ?? null) ? $payload['findings'] : [], 0, 5) as $finding) {
            if (!is_array($finding)) continue;
            $findings[] = [
                'severity' => (string) ($finding['severity'] ?? ''),
                'summary' => (string) ($finding['summary'] ?? ''),
                'target' => isset($finding['target']) ? (string) $finding['target'] : null,
            ];
        }

        $producers = [];
        foreach (is_array($payload['producers'] ?? null) ? $payload['producers'] : [] as $producer) {
            if (!is_array($producer)) continue;
            $producers[] = [
                'source' => (string) ($producer['source'] ?? ''),
                'availability' => (string) ($producer['availability'] ?? ''),
            ];
        }

        return [
            'available' => true,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? 'Health status unavailable',
            'message' => $findings === [] ? 'No material health findings were reported.' : 'Review the reported health findings.',
            'findings' => $findings,
            'producers' => $producers,
        ];
    }
}
