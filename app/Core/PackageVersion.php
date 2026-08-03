<?php

namespace Copot\Core;

final class PackageVersion
{
    private const PATTERN = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/D';

    private function __construct()
    {
    }

    public static function isValid(string $version): bool
    {
        try {
            self::parse($version);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function compare(string $left, string $right): int
    {
        $leftParts = self::parse($left);
        $rightParts = self::parse($right);

        for ($index = 0; $index < 3; $index++) {
            $comparison = self::compareNumeric($leftParts['core'][$index], $rightParts['core'][$index]);

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        $leftPrerelease = $leftParts['prerelease'];
        $rightPrerelease = $rightParts['prerelease'];

        if ($leftPrerelease === [] && $rightPrerelease !== []) {
            return 1;
        }

        if ($leftPrerelease !== [] && $rightPrerelease === []) {
            return -1;
        }

        $count = min(count($leftPrerelease), count($rightPrerelease));

        for ($index = 0; $index < $count; $index++) {
            $leftIdentifier = $leftPrerelease[$index];
            $rightIdentifier = $rightPrerelease[$index];
            $leftNumeric = ctype_digit($leftIdentifier);
            $rightNumeric = ctype_digit($rightIdentifier);

            if ($leftNumeric && $rightNumeric) {
                $comparison = self::compareNumeric($leftIdentifier, $rightIdentifier);
            } elseif ($leftNumeric !== $rightNumeric) {
                $comparison = $leftNumeric ? -1 : 1;
            } else {
                $comparison = strcmp($leftIdentifier, $rightIdentifier);
                $comparison = $comparison <=> 0;
            }

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($leftPrerelease) <=> count($rightPrerelease);
    }

    public static function assertValid(string $version): void
    {
        self::parse($version);
    }

    /**
     * @return array{core: list<string>, prerelease: list<string>, build: list<string>}
     */
    private static function parse(string $version): array
    {
        if (preg_match(self::PATTERN, $version, $matches) !== 1) {
            throw new \InvalidArgumentException('Package version is invalid.');
        }

        $prerelease = isset($matches[4]) && $matches[4] !== ''
            ? explode('.', $matches[4])
            : [];

        foreach ($prerelease as $identifier) {
            if (ctype_digit($identifier) && strlen($identifier) > 1 && $identifier[0] === '0') {
                throw new \InvalidArgumentException('Package version is invalid.');
            }
        }

        return [
            'core' => [$matches[1], $matches[2], $matches[3]],
            'prerelease' => $prerelease,
            'build' => isset($matches[5]) && $matches[5] !== '' ? explode('.', $matches[5]) : [],
        ];
    }

    private static function compareNumeric(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }
}
