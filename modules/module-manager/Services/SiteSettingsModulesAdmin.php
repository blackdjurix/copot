<?php

use Copot\Core\ModulePackageOperator;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\User;

/**
 * Site Settings adapter for the existing Module lifecycle authority.
 * It owns only canonical projection paths and operator-facing transport.
 */
final class SiteSettingsModulesAdmin
{
    private const ACTIONS = ['install', 'enable', 'disable', 'uninstall'];

    public function __construct(
        private object $app,
        private ModuleManagerAdmin $authority
    ) {
    }

    public function inventory(): array
    {
        return $this->authority->projectionInventory();
    }

    public function detail(string $name): ?array
    {
        return $this->authority->projectionDetail($name);
    }

    public function actionPaths(): array
    {
        $paths = [];

        foreach (self::ACTIONS as $action) {
            $paths[$action] = $this->settingsPath() . '/modules/' . $action;
        }

        return $paths;
    }

    public function packagePath(): string
    {
        return $this->settingsPath() . '/modules/package';
    }

    public function lifecyclePath(): string
    {
        return $this->settingsPath() . '/modules/package/lifecycle';
    }

    public function detailPath(string $name): string
    {
        return $this->settingsPath() . '/modules/' . rawurlencode($name);
    }

    public function authorize(Request $request): User|Response
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

    public function mutationResponse(Request $request, string $action): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        if (!in_array($action, self::ACTIONS, true)) {
            return $this->app->adminErrors()->response($request, 404);
        }

        $csrfResponse = $this->app->csrf()->validateOrReject($request);

        if ($csrfResponse instanceof Response) {
            return $this->app->adminErrors()->response($request, 419);
        }

        $name = $request->post('module');

        if (!is_string($name) || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) !== 1) {
            return $this->redirectError('invalid_module_name');
        }

        try {
            $item = $this->find($name);

            if ($item === null) {
                return $this->app->adminErrors()->response($request, 404);
            }

            if ($name === 'module-manager' && in_array($action, ['disable', 'uninstall'], true)) {
                return $this->redirectError('module_manager_self_management_denied');
            }

            $eligibility = $item['available_actions'][$action] ?? null;

            if (!is_array($eligibility) || ($eligibility['enabled'] ?? false) !== true) {
                $reason = $item['denial_reasons'][$action][0] ?? 'action_not_allowed';

                return $this->redirectError((string) $reason);
            }

            match ($action) {
                'install' => $this->app->modules()->install($name),
                'enable' => $this->app->modules()->enable($name),
                'disable' => $this->app->modules()->disable($name),
                'uninstall' => $this->app->modules()->uninstall($name),
            };
        } catch (Throwable) {
            return $this->redirectError('module_action_failed');
        }

        $target = $request->post('return_context') === 'detail'
            ? $this->detailPath($name)
            : $this->settingsPath();

        return Response::redirect($target . '?module_notice=' . rawurlencode($action . '_success') . '#modules');
    }

    public function packageResponse(Request $request): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        if ($this->app->csrf()->validateOrReject($request) instanceof Response) {
            return $this->app->adminErrors()->response($request, 419);
        }

        try {
            $file = $request->file('module_package');

            if (!is_array($file)) {
                throw new InvalidArgumentException('A local Module package ZIP is required.');
            }

            (new ModulePackageOperator($this->app))->registerUpload($file);

            return Response::redirect($this->settingsPath() . '?module_notice=module_package_registered#modules');
        } catch (InvalidArgumentException) {
            return $this->redirectError('module_package_invalid');
        } catch (Throwable) {
            return $this->redirectError('module_package_failed');
        }
    }

    public function lifecycleResponse(Request $request): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        if ($this->app->csrf()->validateOrReject($request) instanceof Response) {
            return $this->app->adminErrors()->response($request, 419);
        }

        $candidate = $request->post('candidate');

        if (!is_string($candidate) || preg_match('/^[a-f0-9]{64}$/', $candidate) !== 1) {
            return $this->redirectError('module_candidate_invalid');
        }

        try {
            $classification = (new ModulePackageOperator($this->app))->execute($candidate);

            return Response::redirect($this->settingsPath() . '?module_notice=module_' . rawurlencode($classification) . '_success#modules');
        } catch (Throwable) {
            return $this->redirectError('module_lifecycle_failed');
        }
    }

    private function find(string $name): ?array
    {
        foreach ($this->inventory() as $item) {
            if (($item['name'] ?? null) === $name) {
                return $item;
            }
        }

        return null;
    }

    private function redirectError(string $code): Response
    {
        return Response::redirect($this->settingsPath() . '?module_error=' . rawurlencode($code) . '#modules');
    }

    private function settingsPath(): string
    {
        return $this->app->adminUrl()->childUrl('settings');
    }
}
