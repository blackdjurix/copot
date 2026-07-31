<?php

final class MediaStagedFile
{
    public function __construct(private string $path, private string $token) {}
    public function path(): string { return $this->path; }
    public function token(): string { return $this->token; }
}
