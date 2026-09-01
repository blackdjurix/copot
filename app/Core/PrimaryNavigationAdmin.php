<?php

namespace Copot\Core;

final class PrimaryNavigationAdmin
{
    public const MENU_NAME = 'Primary Navigation';
    public const MENU_SLUG = 'primary';

    public function __construct(
        private Database $database,
        private NavigationRepository $repository,
        private NavigationService $service,
        private ContentRepository $content
    ) {
    }

    public function ensureMenu(): NavigationMenu
    {
        $menu = $this->repository->canonicalPrimaryMenu();
        if ($menu !== null) return $menu;
        $id = $this->service->createMenu(['name' => self::MENU_NAME, 'slug' => self::MENU_SLUG]);
        $menu = $this->repository->findMenu($id);
        if ($menu === null) throw new \RuntimeException('Primary navigation could not be initialized.');
        return $menu;
    }

    public function validateTarget(array $data): array
    {
        $kind = strtolower(trim((string) ($data['target_kind'] ?? '')));
        $reference = $data['target_reference'] ?? null;
        $customUrl = $data['custom_url'] ?? null;
        if ($kind === 'custom') {
            return $data;
        }
        if ($kind === 'article_collection') {
            if ($reference !== 'articles' || $customUrl !== null) throw new \InvalidArgumentException('Article Collection target is invalid.');
            return $data;
        }
        if ($kind !== 'content' || !is_string($reference) || trim($reference) === '' || $customUrl !== null) {
            throw new \InvalidArgumentException('Navigation target is invalid.');
        }
        $content = $this->content->findPublishedBySlug(trim($reference));
        if (!$content instanceof Content || !in_array($content->type(), ['page', 'article'], true)) {
            throw new \InvalidArgumentException('Published Content target was not found.');
        }
        return $data;
    }

    public function createItem(array $data): int
    {
        $this->validateTarget($data);
        return $this->service->createItem($data);
    }

    public function updateItem(int $id, array $data): void
    {
        $this->validateTarget($data);
        $this->service->updateItem($id, $data);
    }
}
