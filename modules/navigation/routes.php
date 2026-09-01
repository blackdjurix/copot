<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/NavigationMenu.php';
require_once __DIR__ . '/Services/NavigationItem.php';
require_once __DIR__ . '/Services/NavigationRepository.php';
require_once __DIR__ . '/Services/NavigationService.php';

$navigationAdminBase = $app->adminUrl()->baseUrl();
$navigationAdminUrlService = $app->adminUrl();
$navigationAdminUrl = static function (string $path = '') use ($navigationAdminUrlService): string {
    $path = trim($path, '/');
    if ($path === 'navigation' || str_starts_with($path, 'navigation/')) {
        $path = 'navigation-manager' . substr($path, strlen('navigation'));
    }
    return $navigationAdminUrlService->childUrl($path);
};
$navigationPath = $navigationAdminUrl('navigation-manager');
$navigationRepository = new NavigationRepository($app->database());
$navigationService = new NavigationService($app->database(), $navigationRepository);

$app->adminNavigation()->add('Navigation', $navigationPath, 'navigation.manage', 'navigation', 60);

$navigationRenderView = static function (string $view, array $data = []) use ($navigationAdminUrl): string {
    $file = __DIR__ . '/views/admin/' . $view . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Navigation admin view [{$view}] was not found.");
    }

    $data['adminUrl'] = $navigationAdminUrl;
    extract($data, EXTR_SKIP);
    $initialOutputLevel = ob_get_level();

    if (!@ob_start()) {
        throw new RuntimeException('Navigation admin view output buffer is unavailable.');
    }

    try {
        require $file;

        if (ob_get_level() !== $initialOutputLevel + 1) {
            throw new RuntimeException('Navigation admin view output buffer state is invalid.');
        }

        $rendered = @ob_get_clean();

        if (!is_string($rendered)) {
            throw new RuntimeException('Navigation admin view output buffer could not be read.');
        }

        return $rendered;
    } catch (Throwable $exception) {
        while (ob_get_level() > $initialOutputLevel) {
            $level = ob_get_level();

            if (!@ob_end_clean() || ob_get_level() >= $level) {
                break;
            }
        }

        throw $exception;
    }
};

$navigationRequireAdmin = static function ($request) use ($app, $navigationAdminBase) {
    if (!$app->auth()->check()) {
        return Response::redirect($navigationAdminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can('admin.access') || !$user->can('navigation.manage')) {
        return $app->adminErrors()->response($request, 403);
    }

    return $user;
};

$navigationRenderAdmin = static function (
    string $title,
    string $content,
    $user,
    string $currentPath,
    int $status = 200
) use ($app): Response {
    return Response::html($app->adminPageRenderer()->render(
        $title,
        $content,
        $user,
        $app->csrf()->token(),
        $currentPath
    ), $status);
};

$navigationRouteId = static function (mixed $value): ?int {
    if (!is_int($value) && !is_string($value)) {
        return null;
    }

    $raw = (string) $value;

    if (preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
        return null;
    }

    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return is_int($id) && (string) $id === $raw ? $id : null;
};

$navigationScalar = static function ($request, string $field, mixed $default = ''): array {
    $value = $request->post($field, $default);

    return [$value, $value === null || is_scalar($value)];
};

$navigationMenuData = static function ($request): array {
    [$name, $nameValid] = [$request->post('name', ''), true];
    [$slug, $slugValid] = [$request->post('slug', ''), true];
    $errors = [];

    if (!is_scalar($name)) {
        $nameValid = false;
        $name = '';
    }

    if (!is_scalar($slug)) {
        $slugValid = false;
        $slug = '';
    }

    $data = ['name' => trim((string) $name), 'slug' => trim((string) $slug)];

    if (!$nameValid || $data['name'] === '') {
        $errors['name'][] = 'Menu name is required.';
    }

    if (!$slugValid) {
        $errors['slug'][] = 'Menu slug is invalid.';
    }

    return [$data, $errors];
};

$navigationItemFormData = static function (?NavigationItem $item = null): array {
    if (!$item) {
        return [
            'id' => null,
            'label' => '',
            'parent_id' => null,
            'target_mode' => 'custom',
            'target_kind' => '',
            'target_reference' => '',
            'custom_url' => '',
            'is_visible' => true,
        ];
    }

    return [
        'id' => $item->id(),
        'label' => $item->label(),
        'parent_id' => $item->parentId(),
        'target_mode' => $item->targetKind() === 'custom' ? 'custom' : 'provider',
        'target_kind' => $item->targetKind() === 'custom' ? '' : $item->targetKind(),
        'target_reference' => $item->targetReference() ?? '',
        'custom_url' => $item->customUrl() ?? '',
        'is_visible' => $item->isVisible(),
    ];
};

$navigationItemData = static function ($request, int $menuId): array {
    $errors = [];
    $read = static function (string $field, mixed $default = '') use ($request, &$errors): string {
        $value = $request->post($field, $default);

        if ($value !== null && !is_scalar($value)) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is invalid.';
            return '';
        }

        return trim((string) $value);
    };

    $parent = $request->post('parent_id', '');
    $parentId = null;
    if ($parent !== '' && $parent !== null) {
        if (!is_scalar($parent) || preg_match('/^[1-9][0-9]*$/D', (string) $parent) !== 1) {
            $errors['parent_id'][] = 'Parent item is invalid.';
        } else {
            $parentId = (int) $parent;
        }
    }

    $mode = $read('target_mode', 'custom');
    $label = $read('label');
    $data = [
        'menu_id' => $menuId,
        'parent_id' => $parentId,
        'label' => $label,
        'target_kind' => 'custom',
        'target_reference' => null,
        'custom_url' => null,
        'is_visible' => $request->post('is_visible') === '1',
    ];

    if ($label === '') {
        $errors['label'][] = 'Item label is required.';
    }

    if (!in_array($mode, ['custom', 'provider'], true)) {
        $errors['target_mode'][] = 'Target type is invalid.';
    } elseif ($mode === 'custom') {
        $data['custom_url'] = $read('custom_url');
        if ($data['custom_url'] === '') {
            $errors['custom_url'][] = 'Custom URL is required.';
        }
    } else {
        $data['target_kind'] = strtolower($read('target_kind'));
        $data['target_reference'] = $read('target_reference');
        if ($data['target_kind'] === '') {
            $errors['target_kind'][] = 'Provider kind is required.';
        }
        if ($data['target_reference'] === '') {
            $errors['target_reference'][] = 'Provider reference is required.';
        }
    }

    return [$data, $errors];
};

$navigationParentCandidates = static function (array $items, ?int $currentId = null): array {
    $byParent = [];
    foreach ($items as $item) {
        $byParent[$item->parentId() ?? 0][] = $item;
    }

    $excluded = [];
    if ($currentId !== null) {
        $stack = [$currentId];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($excluded[$id])) {
                continue;
            }
            $excluded[$id] = true;
            foreach ($byParent[$id] ?? [] as $child) {
                $stack[] = $child->id();
            }
        }
    }

    $candidates = [];
    $walk = static function (int $parent, int $depth) use (&$walk, &$candidates, $byParent, $excluded): void {
        foreach ($byParent[$parent] ?? [] as $item) {
            if (isset($excluded[$item->id()])) {
                continue;
            }
            $candidates[] = ['item' => $item, 'depth' => $depth];
            $walk($item->id(), $depth + 1);
        }
    };
    $walk(0, 0);

    return $candidates;
};

$navigationTree = static function (array $items): array {
    $byParent = [];
    foreach ($items as $item) {
        $byParent[$item->parentId() ?? 0][] = $item;
    }

    $tree = [];
    $walk = static function (int $parent, int $depth) use (&$walk, &$tree, $byParent): void {
        foreach ($byParent[$parent] ?? [] as $item) {
            $tree[] = ['item' => $item, 'depth' => $depth];
            $walk($item->id(), $depth + 1);
        }
    };
    $walk(0, 0);

    return $tree;
};

$navigationRenderMenus = static function ($user, array $menus, array $errors = [], string $currentPath = '', ?string $notice = null) use ($app, $navigationRenderView, $navigationRenderAdmin): Response {
    $content = $navigationRenderView('menus', [
        'menus' => $menus,
        'errors' => $errors,
        'csrfToken' => $app->csrf()->token(),
        'notice' => $notice,
    ]);

    return $navigationRenderAdmin('Navigation', $content, $user, $currentPath, $errors === [] ? 200 : 503);
};

$navigationFindMenu = static function ($request, mixed $id) use ($app, $navigationService, $navigationRouteId): array {
    $menuId = $navigationRouteId($id);
    $menu = $menuId === null ? null : $navigationService->findMenu($menuId);

    if (!$menu) {
        return [null, $app->adminErrors()->response($request, 404)];
    }

    return [$menu, null];
};

$app->router()->get($navigationPath, function ($request) use ($app, $navigationRequireAdmin, $navigationService, $navigationRenderMenus): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    try {
        return $navigationRenderMenus($user, $navigationService->menus(), [], $request->path(), match ($request->input('saved')) { '1' => 'saved', 'deleted' => 'deleted', default => null });
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
});

$app->router()->get($navigationAdminUrlService->childUrl('navigation-manager/create'), function ($request) use ($app, $navigationRequireAdmin, $navigationRenderView, $navigationRenderAdmin, $navigationPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }

    $content = $navigationRenderView('menu-form', [
        'formAction' => $navigationPath,
        'heading' => 'Create menu',
        'submitLabel' => 'Create menu',
        'menu' => ['name' => '', 'slug' => ''],
        'errors' => [],
        'csrfToken' => $app->csrf()->token(),
    ]);

    return $navigationRenderAdmin('Create Navigation Menu', $content, $user, $request->path());
});

$app->router()->post($navigationPath, function ($request) use ($app, $navigationRequireAdmin, $navigationService, $navigationMenuData, $navigationRenderView, $navigationRenderAdmin, $navigationPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }

    [$data, $errors] = $navigationMenuData($request);
    if ($errors !== []) {
        $content = $navigationRenderView('menu-form', ['formAction' => $navigationPath, 'heading' => 'Create menu', 'submitLabel' => 'Create menu', 'menu' => $data, 'errors' => $errors, 'csrfToken' => $app->csrf()->token()]);
        return $navigationRenderAdmin('Create Navigation Menu', $content, $user, $request->path(), 422);
    }

    try {
        $navigationService->createMenu($data);
    } catch (InvalidArgumentException) {
        $content = $navigationRenderView('menu-form', ['formAction' => $navigationPath, 'heading' => 'Create menu', 'submitLabel' => 'Create menu', 'menu' => $data, 'errors' => ['slug' => ['Menu slug is already in use.']], 'csrfToken' => $app->csrf()->token()]);
        return $navigationRenderAdmin('Create Navigation Menu', $content, $user, $request->path(), 422);
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($navigationPath . '?saved=1');
});

$app->router()->get($navigationAdminUrlService->childUrl('navigation-manager/{menu}/edit'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationRenderView, $navigationRenderAdmin): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }

    $content = $navigationRenderView('menu-form', [
        'formAction' => $app->adminUrl()->childUrl('navigation-manager/' . $menu->id() . '/edit'),
        'heading' => 'Edit menu',
        'submitLabel' => 'Save menu',
        'menu' => $menu->toArray(),
        'errors' => [],
        'csrfToken' => $app->csrf()->token(),
    ]);

    return $navigationRenderAdmin('Edit Navigation Menu', $content, $user, $request->path());
});

$app->router()->post($navigationAdminUrlService->childUrl('navigation-manager/{menu}/edit'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationMenuData, $navigationRenderView, $navigationRenderAdmin, $navigationPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }

    [$data, $errors] = $navigationMenuData($request);
    $action = $app->adminUrl()->childUrl('navigation-manager/' . $menu->id() . '/edit');
    if ($errors !== []) {
        $content = $navigationRenderView('menu-form', ['formAction' => $action, 'heading' => 'Edit menu', 'submitLabel' => 'Save menu', 'menu' => $data, 'errors' => $errors, 'csrfToken' => $app->csrf()->token()]);
        return $navigationRenderAdmin('Edit Navigation Menu', $content, $user, $request->path(), 422);
    }

    try {
        $navigationService->updateMenu($menu->id(), $data);
    } catch (InvalidArgumentException $exception) {
        $content = $navigationRenderView('menu-form', ['formAction' => $action, 'heading' => 'Edit menu', 'submitLabel' => 'Save menu', 'menu' => $data, 'errors' => ['slug' => [$exception->getMessage()]], 'csrfToken' => $app->csrf()->token()]);
        return $navigationRenderAdmin('Edit Navigation Menu', $content, $user, $request->path(), 422);
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($navigationPath . '?saved=1');
});

$app->router()->post($navigationAdminUrlService->childUrl('navigation-manager/{menu}/delete'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }

    try {
        $navigationService->deleteMenu($menu->id());
    } catch (InvalidArgumentException) {
        return $app->adminErrors()->response($request, 404);
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($navigationPath . '?deleted=1');
});

$navigationItemsPath = static fn (int $menuId): string => $navigationAdminUrlService->childUrl('navigation-manager/' . $menuId . '/items');

$app->router()->get($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationTree, $navigationRenderView, $navigationRenderAdmin, $navigationItemsPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }

    try {
        $items = $navigationService->itemsForMenu($menu->id());
        $siblings = [];
        foreach ($items as $item) {
            $siblings[$item->parentId() ?? 0][] = $item->id();
        }
        $reorderActions = [];
        foreach ($siblings as $parent => $ids) {
            foreach ($ids as $index => $id) {
                if ($index > 0) {
                    $order = $ids;
                    [$order[$index - 1], $order[$index]] = [$order[$index], $order[$index - 1]];
                    $reorderActions[$id]['up'] = ['parent_id' => $parent === 0 ? '' : $parent, 'item_ids' => $order];
                }
                if ($index < count($ids) - 1) {
                    $order = $ids;
                    [$order[$index], $order[$index + 1]] = [$order[$index + 1], $order[$index]];
                    $reorderActions[$id]['down'] = ['parent_id' => $parent === 0 ? '' : $parent, 'item_ids' => $order];
                }
            }
        }
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    $content = $navigationRenderView('items', [
        'menu' => $menu,
        'items' => $navigationTree($items),
        'reorderActions' => $reorderActions,
        'csrfToken' => $app->csrf()->token(),
        'notice' => match ($request->input('saved')) { '1' => 'Navigation item saved.', default => null },
    ]);

    return $navigationRenderAdmin('Items: ' . $menu->name(), $content, $user, $request->path());
});

$app->router()->get($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items/create'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationParentCandidates, $navigationItemFormData, $navigationRenderView, $navigationRenderAdmin): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }
    try {
        $items = $navigationService->itemsForMenu($menu->id());
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    $content = $navigationRenderView('item-form', [
        'menu' => $menu,
        'formAction' => $app->adminUrl()->childUrl('navigation-manager/' . $menu->id() . '/items'),
        'heading' => 'Create item',
        'submitLabel' => 'Create item',
        'item' => $navigationItemFormData(),
        'parentCandidates' => $navigationParentCandidates($items),
        'errors' => [],
        'csrfToken' => $app->csrf()->token(),
    ]);

    return $navigationRenderAdmin('Create Navigation Item', $content, $user, $request->path());
});

$app->router()->post($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationParentCandidates, $navigationItemData, $navigationItemFormData, $navigationRenderView, $navigationRenderAdmin, $navigationItemsPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }
    [$data, $errors] = $navigationItemData($request, $menu->id());

    try {
        $items = $navigationService->itemsForMenu($menu->id());
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    if ($errors === []) {
        try {
            $navigationService->createItem($data);
        } catch (InvalidArgumentException $exception) {
            $errors['form'][] = $exception->getMessage();
        } catch (Throwable) {
            return $app->adminErrors()->response($request, 503);
        }
    }
    if ($errors !== []) {
        $content = $navigationRenderView('item-form', ['menu' => $menu, 'formAction' => $app->adminUrl()->childUrl('navigation-manager/' . $menu->id() . '/items'), 'heading' => 'Create item', 'submitLabel' => 'Create item', 'item' => array_merge($navigationItemFormData(), ['label' => $data['label'], 'parent_id' => $data['parent_id'], 'target_mode' => $data['target_kind'] === 'custom' ? 'custom' : 'provider', 'target_kind' => $data['target_kind'] === 'custom' ? '' : $data['target_kind'], 'target_reference' => $data['target_reference'] ?? '', 'custom_url' => $data['custom_url'] ?? '', 'is_visible' => $data['is_visible']]), 'parentCandidates' => $navigationParentCandidates($items), 'errors' => $errors, 'csrfToken' => $app->csrf()->token()]);
        return $navigationRenderAdmin('Create Navigation Item', $content, $user, $request->path(), 422);
    }

    return Response::redirect($navigationItemsPath($menu->id()) . '?saved=1');
});

$app->router()->get($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items/{item}/edit'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationParentCandidates, $navigationItemFormData, $navigationRenderView, $navigationRenderAdmin, $navigationRouteId): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }
    $itemId = $navigationRouteId($params['item'] ?? null);
    $item = $itemId === null ? null : $navigationService->findItem($itemId);
    if (!$item || $item->menuId() !== $menu->id()) {
        return $app->adminErrors()->response($request, 404);
    }
    try {
        $items = $navigationService->itemsForMenu($menu->id());
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }

    $content = $navigationRenderView('item-form', ['menu' => $menu, 'formAction' => $app->adminUrl()->childUrl('navigation-manager/' . $menu->id() . '/items/' . $item->id() . '/edit'), 'heading' => 'Edit item', 'submitLabel' => 'Save item', 'item' => $navigationItemFormData($item), 'parentCandidates' => $navigationParentCandidates($items, $item->id()), 'errors' => [], 'csrfToken' => $app->csrf()->token()]);
    return $navigationRenderAdmin('Edit Navigation Item', $content, $user, $request->path());
});

$app->router()->post($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items/{item}/edit'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationParentCandidates, $navigationItemData, $navigationItemFormData, $navigationRenderView, $navigationRenderAdmin, $navigationRouteId, $navigationItemsPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }
    $itemId = $navigationRouteId($params['item'] ?? null);
    $item = $itemId === null ? null : $navigationService->findItem($itemId);
    if (!$item || $item->menuId() !== $menu->id()) {
        return $app->adminErrors()->response($request, 404);
    }
    [$data, $errors] = $navigationItemData($request, $menu->id());
    try {
        $items = $navigationService->itemsForMenu($menu->id());
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    if ($errors === []) {
        try {
            $navigationService->updateItem($item->id(), $data);
        } catch (InvalidArgumentException $exception) {
            $errors['form'][] = $exception->getMessage();
        } catch (Throwable) {
            return $app->adminErrors()->response($request, 503);
        }
    }
    if ($errors !== []) {
        $formItem = $navigationItemFormData($item);
        $formItem = array_merge($formItem, ['label' => $data['label'], 'parent_id' => $data['parent_id'], 'target_mode' => $data['target_kind'] === 'custom' ? 'custom' : 'provider', 'target_kind' => $data['target_kind'] === 'custom' ? '' : $data['target_kind'], 'target_reference' => $data['target_reference'] ?? '', 'custom_url' => $data['custom_url'] ?? '', 'is_visible' => $data['is_visible']]);
        $content = $navigationRenderView('item-form', ['menu' => $menu, 'formAction' => $app->adminUrl()->childUrl('navigation-manager/' . $menu->id() . '/items/' . $item->id() . '/edit'), 'heading' => 'Edit item', 'submitLabel' => 'Save item', 'item' => $formItem, 'parentCandidates' => $navigationParentCandidates($items, $item->id()), 'errors' => $errors, 'csrfToken' => $app->csrf()->token()]);
        return $navigationRenderAdmin('Edit Navigation Item', $content, $user, $request->path(), 422);
    }
    return Response::redirect($navigationItemsPath($menu->id()) . '?saved=1');
});

$app->router()->post($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items/{item}/delete'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationItemsPath, $navigationRouteId): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }
    $itemId = $navigationRouteId($params['item'] ?? null);
    $item = $itemId === null ? null : $navigationService->findItem($itemId);
    if (!$item || $item->menuId() !== $menu->id()) {
        return $app->adminErrors()->response($request, 404);
    }
    try {
        $navigationService->deleteItem($item->id());
    } catch (InvalidArgumentException) {
        return $app->adminErrors()->response($request, 404);
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    return Response::redirect($navigationItemsPath($menu->id()) . '?deleted=1');
});

$app->router()->post($navigationAdminUrlService->childUrl('navigation-manager/{menu}/items/reorder'), function ($request, array $params) use ($app, $navigationRequireAdmin, $navigationFindMenu, $navigationService, $navigationItemsPath): Response {
    $user = $navigationRequireAdmin($request);
    if ($user instanceof Response) {
        return $user;
    }
    if ($app->csrf()->validateOrReject($request) instanceof Response) {
        return $app->adminErrors()->response($request, 419);
    }
    [$menu, $failure] = $navigationFindMenu($request, $params['menu'] ?? null);
    if ($failure) {
        return $failure;
    }
    $parent = $request->post('parent_id', '');
    $itemIds = $request->post('item_ids', null);
    if ($parent !== '' && (!is_scalar($parent) || preg_match('/^[1-9][0-9]*$/D', (string) $parent) !== 1)) {
        return $app->adminErrors()->response($request, 422);
    }
    if (!is_array($itemIds) || array_filter($itemIds, static fn ($id): bool => !is_scalar($id) || preg_match('/^[1-9][0-9]*$/D', (string) $id) !== 1) !== []) {
        return $app->adminErrors()->response($request, 422);
    }
    try {
        $navigationService->reorderSiblings($menu->id(), $parent === '' ? null : (int) $parent, array_map('intval', $itemIds));
    } catch (InvalidArgumentException) {
        return $app->adminErrors()->response($request, 422);
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    return Response::redirect($navigationItemsPath($menu->id()) . '?saved=1');
});
