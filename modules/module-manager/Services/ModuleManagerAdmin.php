<?php

use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleRepository;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\User;

final class ModuleManagerAdmin
{
    private const SELF_MANAGEMENT_DENIED = 'module_manager_self_management_denied';
    private const RESERVED_DETAIL_NAMES = ['install', 'enable', 'disable', 'uninstall'];

    public function __construct(private object $app)
    {
    }

    public function inventoryResponse(Request $request): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        return $this->renderInventory($request, $user);
    }

    public function detailResponse(Request $request, string $name): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        if (preg_match('/^[a-z0-9_-]+$/', $name) !== 1
            || in_array($name, self::RESERVED_DETAIL_NAMES, true)) {
            return $this->app->adminErrors()->response($request, 404);
        }

        try {
            $item = $this->findItem($this->inventory(), $name);

            if ($item === null) {
                return $this->app->adminErrors()->response($request, 404);
            }

            return $this->renderDetail($request, $user, $this->presentDenialReasons([$item])[0]);
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 503);
        }
    }

    public function mutationResponse(Request $request, string $action): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        $csrfResponse = $this->app->csrf()->validateOrReject($request);

        if ($csrfResponse instanceof Response) {
            return $this->app->adminErrors()->response($request, 419);
        }

        $name = $request->post('module');
        $returnContext = $request->post('return_context');

        if (!is_string($name) || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) !== 1) {
            return $this->renderInventory($request, $user, 'invalid_module_name', null, 422);
        }

        try {
            $items = $this->inventory();
            $item = $this->findItem($items, $name);

            if ($item === null) {
                return $this->app->adminErrors()->response($request, 404);
            }

            if ($name === 'module-manager' && in_array($action, ['disable', 'uninstall'], true)) {
                return $this->renderInventory($request, $user, self::SELF_MANAGEMENT_DENIED, null, 422);
            }

            $eligibility = $item['available_actions'][$action] ?? null;

            if (!is_array($eligibility) || ($eligibility['enabled'] ?? false) !== true) {
                $reason = $item['denial_reasons'][$action][0] ?? 'action_not_allowed';

                return $this->renderInventory($request, $user, (string) $reason, null, 422);
            }

            match ($action) {
                'install' => $this->app->modules()->install($name),
                'enable' => $this->app->modules()->enable($name),
                'disable' => $this->app->modules()->disable($name),
                'uninstall' => $this->app->modules()->uninstall($name),
                default => throw new InvalidArgumentException('Unsupported module action.'),
            };
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 503);
        }

        $target = $returnContext === 'detail'
            ? $this->modulePath($name)
            : $this->modulesPath();

        return Response::redirect($target . '?notice=' . rawurlencode($action . '_success'));
    }

    private function authorize(Request $request): User|Response
    {
        if (!$this->app->auth()->check()) {
            return Response::redirect($this->app->adminUrl()->baseUrl());
        }

        $user = $this->app->auth()->user();
        $adminPermission = $this->app->config()->get('admin.permission', 'admin.access');

        if (!$user instanceof User || !is_string($adminPermission)
            || !$user->can(trim($adminPermission)) || !$user->can('modules.manage')) {
            return $this->app->adminErrors()->response($request, 403);
        }

        return $user;
    }

    private function renderInventory(
        Request $request,
        User $user,
        ?string $error = null,
        ?string $notice = null,
        int $status = 200
    ): Response {
        try {
            $items = $this->presentDenialReasons($this->inventory());
            $view = __DIR__ . '/../views/admin/modules.php';

            if (!is_file($view)) {
                throw new RuntimeException('Module Manager view is unavailable.');
            }

            $content = $this->renderView($view, [
                'items' => $items,
                'csrfToken' => $this->app->csrf()->token(),
                'inventoryPath' => $this->modulesPath(),
                'detailPath' => fn (string $name): string => $this->modulePath($name),
                'actionPaths' => $this->actionPaths(),
                'error' => $this->messageFor($error),
                'notice' => $notice ?? $this->noticeFor($request->input('notice')),
            ]);

            return Response::html($this->app->adminPageRenderer()->render(
                'Modules',
                $content,
                $user,
                $this->app->csrf()->token(),
                $request->path()
            ), $status);
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 503);
        }
    }

    private function renderDetail(Request $request, User $user, array $item): Response
    {
        try {
            $view = __DIR__ . '/../views/admin/module-detail.php';

            if (!is_file($view)) {
                throw new RuntimeException('Module Manager detail view is unavailable.');
            }

            $content = $this->renderView($view, [
                'item' => $item,
                'csrfToken' => $this->app->csrf()->token(),
                'inventoryPath' => $this->modulesPath(),
                'detailPath' => $this->modulePath((string) ($item['name'] ?? '')),
                'actionPaths' => $this->actionPaths(),
                'error' => $this->messageFor($request->input('error')),
                'notice' => $this->noticeFor($request->input('notice')),
            ]);

            return Response::html($this->app->adminPageRenderer()->render(
                'Module Details',
                $content,
                $user,
                $this->app->csrf()->token(),
                $request->path()
            ));
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 503);
        }
    }

    private function presentDenialReasons(array $items): array
    {
        foreach ($items as &$item) {
            $reasons = is_array($item['denial_reasons'] ?? null)
                ? $item['denial_reasons']
                : [];

            foreach ($reasons as $action => $codes) {
                if (!is_array($codes)) {
                    $reasons[$action] = [$this->denialMessage($codes)];
                    continue;
                }

                $reasons[$action] = array_map(
                    fn (mixed $code): string => $this->denialMessage($code),
                    $codes
                );
            }

            $item['denial_reasons'] = $reasons;
        }
        unset($item);

        return $items;
    }

    private function denialMessage(mixed $code): string
    {
        if (is_string($code)) {
            $message = $this->messageFor($code);

            if ($message !== null) {
                return $message;
            }
        }

        return 'This module action is not currently allowed.';
    }

    private function inventory(): array
    {
        $builder = new ModuleInventoryBuilder(
            new ModuleDiscovery($this->app->path('modules')),
            new ModuleRepository($this->app->database())
        );
        $items = $builder->build();

        foreach ($items as &$item) {
            if (($item['name'] ?? null) !== 'module-manager') {
                continue;
            }

            foreach (['disable', 'uninstall'] as $action) {
                $item['available_actions'][$action]['visible'] = true;
                $item['available_actions'][$action]['enabled'] = false;
                $item['denial_reasons'][$action] = [self::SELF_MANAGEMENT_DENIED];
            }
        }
        unset($item);

        return $items;
    }

    private function findItem(array $items, string $name): ?array
    {
        foreach ($items as $item) {
            if (($item['name'] ?? null) === $name) {
                return $item;
            }
        }

        return null;
    }

    private function renderView(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        $level = ob_get_level();

        if (!@ob_start()) {
            throw new RuntimeException('Module Manager view output buffer is unavailable.');
        }

        try {
            require $file;

            if (ob_get_level() !== $level + 1) {
                throw new RuntimeException('Module Manager view output buffer state is invalid.');
            }

            $rendered = @ob_get_clean();

            if (!is_string($rendered)) {
                throw new RuntimeException('Module Manager view output buffer could not be read.');
            }

            return $rendered;
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                @ob_end_clean();
            }

            throw $exception;
        }
    }

    private function modulesPath(): string
    {
        return $this->app->adminUrl()->childUrl('modules');
    }

    private function modulePath(string $name): string
    {
        return $this->app->adminUrl()->childUrl('modules/' . rawurlencode($name));
    }

    private function actionPaths(): array
    {
        return array_combine(
            ['install', 'enable', 'disable', 'uninstall'],
            array_map(
                fn (string $action): string => $this->app->adminUrl()->childUrl('modules/' . $action),
                ['install', 'enable', 'disable', 'uninstall']
            )
        );
    }

    private function noticeFor(mixed $notice): ?string
    {
        return match ($notice) {
            'install_success' => 'Module installed successfully.',
            'enable_success' => 'Module enabled successfully.',
            'disable_success' => 'Module disabled successfully.',
            'uninstall_success' => 'Module uninstalled successfully.',
            default => null,
        };
    }

    private function messageFor(?string $code): ?string
    {
        return match ($code) {
            'invalid_module_name' => 'The submitted module name is invalid.',
            'already_installed' => 'The module is already installed.',
            'already_enabled' => 'The module is already enabled.',
            'already_disabled' => 'The module is already disabled.',
            'enabled_module' => 'An enabled module must be disabled before uninstalling it.',
            'not_installed' => 'The module is not installed.',
            'malformed_discovery' => 'The module manifest is malformed.',
            'invalid_metadata' => 'The module metadata is invalid.',
            'discovery_missing' => 'The module could not be discovered.',
            'route_file_missing' => 'The declared route file is missing.',
            'listener_file_missing' => 'The declared listener file is missing.',
            'self_dependency' => 'The module declares an invalid self-dependency.',
            'duplicate_dependency' => 'The module declares a duplicate dependency.',
            'unsupported_version_constraint' => 'The module declares an unsupported version constraint.',
            'dependency_missing' => 'A required dependency is missing.',
            'dependency_disabled' => 'A required dependency is disabled.',
            'dependency_cycle' => 'The module dependency graph contains a cycle.',
            'enabled_dependent' => 'An enabled dependent module blocks this action.',
            'dependent_safety_unknown' => 'Dependent-module safety could not be established.',
            'invalid_stored_status' => 'The stored module status is invalid and requires review.',
            self::SELF_MANAGEMENT_DENIED => 'Module Manager cannot disable or uninstall itself.',
            'action_not_allowed' => 'This module action is not currently allowed.',
            default => null,
        };
    }
}
