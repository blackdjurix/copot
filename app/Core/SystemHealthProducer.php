<?php

namespace Copot\Core;

interface SystemHealthProducer
{
    public function source(): string;
    public function required(): bool;
    public function report(SystemHealthContext $context): SystemHealthProducerResult;
}
