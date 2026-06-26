<?php

class TaxonomySlugger
{
    public function unique(string $value, TaxonomyRepository $taxonomy, int $taxonomyTypeId, ?int $ignoreId = null): string
    {
        $base = $this->generate($value);
        $slug = $base;
        $suffix = 2;

        while ($taxonomy->termSlugExists($taxonomyTypeId, $slug, $ignoreId)) {
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
            throw new InvalidArgumentException('Taxonomy slug cannot be empty.');
        }

        return $slug;
    }
}
