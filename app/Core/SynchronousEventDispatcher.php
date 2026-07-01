<?php

namespace Copot\Core;

class SynchronousEventDispatcher implements EventDispatcher
{
    private array $listeners = [];

    public function listen(string $eventName, callable $listener): void
    {
        $this->validateEventName($eventName);
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(string $eventName, object $event): void
    {
        $this->validateEventName($eventName);

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listener($event);
        }
    }

    private function validateEventName(string $eventName): void
    {
        if (preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)+$/D', $eventName) !== 1) {
            throw new \InvalidArgumentException("Event name [{$eventName}] is invalid.");
        }
    }
}
