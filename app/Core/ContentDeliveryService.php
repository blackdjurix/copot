<?php

namespace Copot\Core;

final class ContentDeliveryService
{
    public function __construct(private ContentRepository $repository)
    {
    }

    /** @return array<string,mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->repository->findPublishedBySlug($slug)?->toRenderData();
    }

    /** @return list<array<string,mixed>> */
    public function publishedArticles(): array
    {
        return array_map(static fn (Content $content): array => $content->toRenderData(), $this->repository->publishedArticles());
    }
}
