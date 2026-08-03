<?php

final class FormPublicRequestException extends InvalidArgumentException {}

final class FormPublicRequestValidator
{
    public const MAX_BODY_BYTES = 262144;
    public const MAX_FIELDS = 50;

    public function assertBodySize(): void
    {
        $raw = $_SERVER['CONTENT_LENGTH'] ?? null;
        if ($raw === null || $raw === '') return;
        if (!is_scalar($raw) || preg_match('/^(?:0|[1-9][0-9]*)$/', (string) $raw) !== 1) {
            throw new FormPublicRequestException('The submission request is invalid.');
        }
        if ((int) $raw > self::MAX_BODY_BYTES) {
            throw new FormPublicRequestException('The submission request is too large.');
        }
    }

    public function values(mixed $values): array
    {
        if (!is_array($values) || count($values) > self::MAX_FIELDS) {
            throw new FormPublicRequestException('The submission request is invalid.');
        }
        foreach ($values as $key => $value) {
            if (!is_string($key) || is_array($value) || is_object($value) || is_resource($value)) {
                throw new FormPublicRequestException('The submission request is invalid.');
            }
            if (is_string($value) && preg_match('//u', $value) !== 1) {
                throw new FormPublicRequestException('The submission request is invalid.');
            }
        }
        return $values;
    }

    public function honeypot(mixed $value): void
    {
        if ($value !== null && (!is_scalar($value) || (string) $value !== '')) {
            throw new FormPublicRequestException('The submission could not be accepted.');
        }
    }
}
