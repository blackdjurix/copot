<?php

namespace Copot\Core;

final class SystemHealthReportProvider
{
    private $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    public function report(SystemHealthContext $context): ?SystemHealthReport
    {
        if ($this->resolver === null) return null;

        try {
            $report = ($this->resolver)($context);
            return $report instanceof SystemHealthReport ? $report : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
