<?php

class Slugger
{
    public function unique(string $title, ContentRepository $contents, ?int $ignoreId = null): string
    {
        return $this->generate($title);
    }

    public function generate(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            throw new InvalidArgumentException('Content slug cannot be empty.');
        }

        if (strlen($slug) > 190) {
            throw new InvalidArgumentException('Content slug cannot exceed 190 characters.');
        }

        return $slug;
    }
}
