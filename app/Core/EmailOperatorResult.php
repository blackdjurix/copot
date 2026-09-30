<?php

namespace Copot\Core;

final class EmailOperatorResult
{
    private function __construct(
        private string $status,
        private string $code,
        private array $data = [],
    ) {
    }

    public static function success(string $code, array $data = []): self
    {
        return new self('success', $code, $data);
    }

    public static function failure(string $code): self
    {
        return new self('failure', $code);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function succeeded(): bool
    {
        return $this->status === 'success';
    }

    /** @return array{status: string, code: string, data: array} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'code' => $this->code, 'data' => $this->data];
    }
}
