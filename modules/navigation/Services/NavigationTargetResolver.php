<?php

interface NavigationTargetResolver
{
    public function kind(): string;

    public function resolve(string $reference): ?NavigationRenderItem;
}
