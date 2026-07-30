<?php

use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ThemeDefinition;
use Copot\Core\ThemeException;
use Copot\Core\User;

final class ThemeManagerAdmin
{
    private const MAX_SCREENSHOT_BYTES = 8388608;
    private const SCREENSHOT_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private object $app)
    {
    }

    public function inventoryResponse(Request $request): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        try {
            return $this->renderInventory($request, $user);
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 503);
        }
    }

    public function activateResponse(Request $request, string $routeThemeId): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        $csrfResponse = $this->app->csrf()->validateOrReject($request);
        if ($csrfResponse instanceof Response) {
            return $this->app->adminErrors()->response($request, 419);
        }

        if (preg_match('/^[a-z0-9-]+$/D', $routeThemeId) !== 1) {
            return $this->app->adminErrors()->response($request, 422);
        }

        $submittedThemeId = $request->post('theme_id');
        if (!is_string($submittedThemeId)
            || preg_match('/^[a-z0-9-]+$/D', $submittedThemeId) !== 1
            || $submittedThemeId !== $routeThemeId) {
            return $this->app->adminErrors()->response($request, 422);
        }

        try {
            $this->app->themeLifecycle()->activate($routeThemeId);
        } catch (ThemeException) {
            return $this->renderInventory($request, $user, 'Activation could not be completed for this Theme.', 422);
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 503);
        }

        return Response::redirect($this->app->adminUrl()->childUrl('themes') . '?notice=activated');
    }

    public function screenshotResponse(Request $request, string $themeId): Response
    {
        $user = $this->authorize($request);

        if ($user instanceof Response) {
            return $user;
        }

        if (preg_match('/^[a-z0-9-]+$/D', $themeId) !== 1) {
            return $this->app->adminErrors()->response($request, 404);
        }

        try {
            $inventory = $this->app->themeLifecycle()->inventory();
            foreach ($inventory['themes'] as $item) {
                if (($item['theme_id'] ?? null) !== $themeId || !($item['definition'] ?? null) instanceof ThemeDefinition) {
                    continue;
                }

                $definition = $item['definition'];
                $relative = $definition->screenshot();
                if ($relative === null) {
                    return $this->app->adminErrors()->response($request, 404);
                }

                $path = $this->containedScreenshotPath($definition, $relative);
                if ($path === null) {
                    return $this->app->adminErrors()->response($request, 404);
                }

                $content = file_get_contents($path);
                $mime = $this->screenshotMimeType($path);
                if ($content === false || $mime === null || strlen($content) > self::MAX_SCREENSHOT_BYTES) {
                    return $this->app->adminErrors()->response($request, 404);
                }

                return Response::content($content, 200, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        } catch (Throwable) {
            return $this->app->adminErrors()->response($request, 404);
        }

        return $this->app->adminErrors()->response($request, 404);
    }

    private function authorize(Request $request): User|Response
    {
        if (!$this->app->auth()->check()) {
            return Response::redirect($this->app->adminUrl()->baseUrl());
        }

        $user = $this->app->auth()->user();
        $adminPermission = $this->app->config()->get('admin.permission', 'admin.access');

        if (!$user instanceof User || !is_string($adminPermission)
            || !$user->can(trim($adminPermission)) || !$user->can('themes.manage')) {
            return $this->app->adminErrors()->response($request, 403);
        }

        return $user;
    }

    private function renderInventory(Request $request, User $user, ?string $error = null, int $status = 200): Response
    {
        $inventory = $this->app->themeLifecycle()->inventory();
        $content = $this->renderView(__DIR__ . '/../views/admin/themes.php', [
            'items' => $inventory['themes'],
            'diagnostics' => $inventory['diagnostics'],
            'csrfToken' => $this->app->csrf()->token(),
            'inventoryPath' => $this->app->adminUrl()->childUrl('themes'),
            'activationPath' => fn (string $id): string => $this->app->adminUrl()->childUrl('themes/' . $id . '/activate'),
            'screenshotPath' => fn (string $id): string => $this->app->adminUrl()->childUrl('themes/' . $id . '/screenshot'),
            'settingsPath' => fn (string $id): string => $this->app->adminUrl()->childUrl('themes/' . $id . '/settings'),
            'notice' => $this->noticeFor($request->input('notice')),
            'error' => $error,
        ]);

        return Response::html($this->app->adminPageRenderer()->render(
            'Themes',
            $content,
            $user,
            $this->app->csrf()->token(),
            $request->path()
        ), $status);
    }

    private function renderView(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('Theme Admin view is unavailable.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function containedScreenshotPath(ThemeDefinition $definition, string $relative): ?string
    {
        $relative = str_replace(['\\', "\0"], ['/', ''], trim($relative));
        if ($relative === '' || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:\//', $relative)) {
            return null;
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $extension = strtolower((string) pathinfo($relative, PATHINFO_EXTENSION));
        if (!isset(self::SCREENSHOT_MIME_TYPES[$extension])) {
            return null;
        }

        $root = realpath($definition->path());
        $candidate = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($root === false || $candidate === false || !is_file($candidate)) {
            return null;
        }

        $size = filesize($candidate);
        if ($size === false || $size > self::MAX_SCREENSHOT_BYTES) {
            return null;
        }

        return str_starts_with($candidate, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) ? $candidate : null;
    }

    private function screenshotMimeType(string $path): ?string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $expected = self::SCREENSHOT_MIME_TYPES[$extension] ?? null;
        if ($expected === null) {
            return null;
        }

        $mime = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $mime === false ? false : finfo_file($mime, $path);
        if ($mime !== false) {
            finfo_close($mime);
        }

        return $detected === $expected ? $expected : null;
    }

    private function noticeFor(mixed $notice): ?string
    {
        return $notice === 'activated' ? 'Theme activated successfully. The public frontend appearance has changed.' : null;
    }
}
