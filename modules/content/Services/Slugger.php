<?php

class Slugger
{
    public function unique(string $title, ContentRepository $contents, ?int $ignoreId = null): string
    {
        $base = $this->generate($title);
        $slug = $base;
        $suffix = 2;

        while ($contents->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    public function generate(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            throw new InvalidArgumentException('Content slug cannot be empty.');
        }

        return $slug;
    }
}
