<?php

namespace Copot\Core;

final class Slugger
{
    public function generate(string $value): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($value))) ?? '', '-');
        if ($slug === '') throw new \InvalidArgumentException('Content slug cannot be empty.');
        if (strlen($slug) > 190) throw new \InvalidArgumentException('Content slug cannot exceed 190 characters.');
        return $slug;
    }

    public function unique(string $title, ContentRepository $contents, ?int $ignoreId = null): string
    {
        return $this->generate($title);
    }
}
