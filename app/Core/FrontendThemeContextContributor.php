<?php

namespace Copot\Core;

interface FrontendThemeContextContributor
{
    public function contextKey(): string;

    public function contribute(FrontendThemeContext $context): array;
}
