<?php

namespace Copot\Core;

final class InstallerIntent
{
    public const FRESH = 'fresh_installation';
    public const COEXIST = 'coexistence';
    public const ADOPT = 'adopt_existing_installation';

    public static function all(): array
    {
        return [self::FRESH, self::COEXIST, self::ADOPT];
    }
}
