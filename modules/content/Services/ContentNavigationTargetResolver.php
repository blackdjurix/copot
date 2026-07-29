<?php

final class ContentNavigationTargetResolver implements NavigationTargetResolver
{
    public function __construct(private ContentRepository $content)
    {
    }

    public function kind(): string
    {
        return 'content';
    }

    public function resolve(string $reference): ?NavigationRenderItem
    {
        $reference = trim($reference);

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $reference) !== 1) {
            return null;
        }

        try {
            $content = $this->content->findPublishedBySlug($reference);
        } catch (Throwable) {
            return null;
        }

        if (!$content instanceof Content) {
            return null;
        }

        $slug = $content->slug();

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        return new NavigationRenderItem(
            'content',
            $slug,
            $content->title(),
            '/content/' . $slug,
            true
        );
    }
}
