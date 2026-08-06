<?php

namespace Copot\Core;

final class ReleaseMetadataValidator
{
    /** @var list<string> */
    private const TRANSITIONS = ['INSTALL', 'PATCH', 'UPDATE', 'UPGRADE'];

    private function __construct()
    {
    }

    public static function validate(array $metadata): void
    {
        if (array_keys($metadata) !== ['transition', 'whats_new']) {
            throw new \InvalidArgumentException('Release metadata fields are invalid.');
        }

        if (!is_string($metadata['transition']) || !in_array($metadata['transition'], self::TRANSITIONS, true)) {
            throw new \InvalidArgumentException('Release metadata transition is invalid.');
        }

        if (!is_array($metadata['whats_new']) || $metadata['whats_new'] === []) {
            throw new \InvalidArgumentException("Release metadata What's New entries are invalid.");
        }

        if (array_keys($metadata['whats_new']) !== range(0, count($metadata['whats_new']) - 1)) {
            throw new \InvalidArgumentException("Release metadata What's New entries are invalid.");
        }

        foreach ($metadata['whats_new'] as $entry) {
            if (!is_string($entry) || trim($entry) === '' || trim($entry) !== $entry) {
                throw new \InvalidArgumentException("Release metadata What's New entry is invalid.");
            }
        }
    }
}
