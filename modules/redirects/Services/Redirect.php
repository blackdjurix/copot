<?php

final class Redirect
{
    public function __construct(
        private int $id,
        private string $sourcePath,
        private string $target,
        private int $statusCode,
        private string $createdAt,
        private string $updatedAt
    ) {
    }

    public function id(): int { return $this->id; }
    public function sourcePath(): string { return $this->sourcePath; }
    public function target(): string { return $this->target; }
    public function statusCode(): int { return $this->statusCode; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_path' => $this->sourcePath,
            'target' => $this->target,
            'status_code' => $this->statusCode,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
