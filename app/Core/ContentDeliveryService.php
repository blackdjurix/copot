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
}
