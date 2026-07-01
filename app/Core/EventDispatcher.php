<?php

namespace Copot\Core;

interface EventDispatcher
{
    public function listen(string $eventName, callable $listener): void;

    public function dispatch(string $eventName, object $event): void;
}
