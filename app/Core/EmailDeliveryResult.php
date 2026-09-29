<?php

namespace Copot\Core;

final class EmailDeliveryResult
{
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';

    private function __construct(
        private string $status,
        private string $code,
    ) {
    }

    public static function succeeded(): self
    {
        return new self(self::SUCCEEDED, 'delivered');
    }

    public static function failed(string $code): self
    {
        if (!in_array($code, ['message_invalid', 'configuration_incomplete', 'credential_unavailable', 'transport_failed'], true)) {
            $code = 'transport_failed';
        }

        return new self(self::FAILED, $code);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    /** @return array{status: string, code: string} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'code' => $this->code];
    }
}
