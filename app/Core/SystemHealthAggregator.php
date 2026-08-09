<?php

namespace Copot\Core;

final class SystemHealthAggregator
{
    /** @param list<SystemHealthProducer> $producers */
    public function aggregate(SystemHealthContext $context, array $producers): SystemHealthReport
    {
        $results = [];
        $findings = [];
        $insufficientEvidence = false;

        foreach ($producers as $producer) {
            if (!$producer instanceof SystemHealthProducer) {
                throw new \InvalidArgumentException('System Health producer list contains an invalid producer.');
            }

            $source = $this->safeSource($producer);
            $required = $this->safeRequired($producer);

            try {
                $result = $producer->report($context);
                if (!$result instanceof SystemHealthProducerResult || $result->source() !== $source) {
                    throw new \RuntimeException('System Health producer returned an invalid result.');
                }
            } catch (\Throwable) {
                $result = SystemHealthProducerResult::producerError($source, $required);
            }

            try {
                $visible = $result->visibleTo($context->viewer());
            } catch (\Throwable) {
                $visible = false;
            }
            if (!$visible) {
                continue;
            }

            $results[] = $result;
            if ($result->required() && !SystemHealthProducerAvailability::isEvidenceSufficient($result->availability())) {
                $insufficientEvidence = true;
            }
            foreach ($result->findings() as $finding) {
                $findings[] = $finding;
            }
        }

        usort($results, static fn (SystemHealthProducerResult $left, SystemHealthProducerResult $right): int => strcmp($left->source(), $right->source()));
        usort($findings, static function (SystemHealthFinding $left, SystemHealthFinding $right): int {
            $severity = SystemHealthFindingSeverity::rank($right->severity()) <=> SystemHealthFindingSeverity::rank($left->severity());
            if ($severity !== 0) { return $severity; }

            return strcmp(implode('|', [$left->source(), $left->code(), $left->target() ?? '', $left->identity()]), implode('|', [$right->source(), $right->code(), $right->target() ?? '', $right->identity()]));
        });

        $status = $this->status($findings, $insufficientEvidence);

        return new SystemHealthReport($context, $status, $results, $findings);
    }

    private function status(array $findings, bool $insufficientEvidence): string
    {
        if ($findings !== []) {
            return match (SystemHealthFindingSeverity::rank($findings[0]->severity())) {
                3 => SystemHealthOverallStatus::CRITICAL,
                2 => SystemHealthOverallStatus::DEGRADED,
                default => SystemHealthOverallStatus::ATTENTION_REQUIRED,
            };
        }

        return $insufficientEvidence ? SystemHealthOverallStatus::ATTENTION_REQUIRED : SystemHealthOverallStatus::OPERATIONAL;
    }

    private function safeSource(SystemHealthProducer $producer): string
    {
        try {
            $source = $producer->source();
            if (preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $source) === 1) { return $source; }
        } catch (\Throwable) {
        }

        return 'unknown-producer';
    }

    private function safeRequired(SystemHealthProducer $producer): bool
    {
        try { return $producer->required(); } catch (\Throwable) { return false; }
    }
}
