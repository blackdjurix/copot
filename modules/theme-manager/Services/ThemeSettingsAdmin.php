<?php

use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ThemeDefinition;
use Copot\Core\ThemeException;
use Copot\Core\ThemeSettingsService;
use Copot\Core\ThemeSettingsValidationException;
use Copot\Core\User;

final class ThemeSettingsAdmin
{
    private ThemeSettingsService $settings;
    public function __construct(private object $app)
    {
        $this->settings = new ThemeSettingsService(new Copot\Core\SettingsRepository($app->database()), $app->database());
    }

    public function showResponse(Request $request, string $themeId): Response
    {
        $user = $this->authorize($request); if ($user instanceof Response) return $user;
        try { [$item, $theme] = $this->healthy($themeId); return $this->render($request, $user, $themeId, $item, $theme, $this->settings->values($theme)); }
        catch (ThemeException) { return $this->app->adminErrors()->response($request, 404); }
        catch (Throwable) { return $this->app->adminErrors()->response($request, 503); }
    }

    public function saveResponse(Request $request, string $themeId): Response
    {
        $user = $this->authorize($request); if ($user instanceof Response) return $user;
        if ($this->app->csrf()->validateOrReject($request) instanceof Response) return $this->app->adminErrors()->response($request, 419);
        if (!$this->validId($themeId) || $this->submittedId($request) !== $themeId) return $this->app->adminErrors()->response($request, 422);
        try {
            [, $theme] = $this->healthy($themeId);
            $submitted = $request->post('settings');
            if (!is_array($submitted)) return $this->app->adminErrors()->response($request, 422);
            $this->settings->save($theme, $submitted);
            return Response::redirect($this->app->adminUrl()->childUrl('themes/' . $themeId . '/settings') . '?saved=1');
        } catch (ThemeSettingsValidationException $exception) {
            try { [$item, $theme] = $this->healthy($themeId); return $this->render($request, $user, $themeId, $item, $theme, $exception->submitted(), $exception->fieldErrors(), ['Invalid values were not saved.'], 422); }
            catch (Throwable) { return $this->app->adminErrors()->response($request, 503); }
        } catch (ThemeException) { return $this->app->adminErrors()->response($request, 422); }
        catch (Throwable) { return $this->app->adminErrors()->response($request, 503); }
    }

    public function resetResponse(Request $request, string $themeId): Response
    {
        $user = $this->authorize($request); if ($user instanceof Response) return $user;
        if ($this->app->csrf()->validateOrReject($request) instanceof Response) return $this->app->adminErrors()->response($request, 419);
        if (!$this->validId($themeId) || $this->submittedId($request) !== $themeId) return $this->app->adminErrors()->response($request, 422);
        try { [, $theme] = $this->healthy($themeId); $this->settings->reset($theme); return Response::redirect($this->app->adminUrl()->childUrl('themes/' . $themeId . '/settings') . '?reset=1'); }
        catch (ThemeException) { return $this->app->adminErrors()->response($request, 422); }
        catch (Throwable) { return $this->app->adminErrors()->response($request, 503); }
    }

    private function healthy(string $id): array
    {
        if (!$this->validId($id)) throw new ThemeException('Invalid Theme.');
        $inventory = $this->app->themeLifecycle()->inventory();
        foreach ($inventory['themes'] as $item) if (($item['theme_id'] ?? null) === $id && ($item['discovery_status'] ?? null) === 'healthy' && ($item['definition'] ?? null) instanceof ThemeDefinition) return [$item, $item['definition']];
        throw new ThemeException('Theme is unavailable.');
    }
    private function authorize(Request $request): User|Response
    {
        if (!$this->app->auth()->check()) return Response::redirect($this->app->adminUrl()->baseUrl());
        $user = $this->app->auth()->user(); $permission = $this->app->config()->get('admin.permission', 'admin.access');
        return $user instanceof User && is_string($permission) && $user->can(trim($permission)) && $user->can('themes.manage') ? $user : $this->app->adminErrors()->response($request, 403);
    }
    private function validId(string $id): bool { return preg_match('/^[a-z0-9-]+$/D', $id) === 1; }
    private function submittedId(Request $request): ?string { $id = $request->post('theme_id'); return is_string($id) && $this->validId($id) ? $id : null; }
    private function render(Request $request, User $user, string $id, array $item, ThemeDefinition $theme, array $values, array $errors = [], array $formErrors = [], int $status = 200): Response
    {
        $content = $this->view(__DIR__ . '/../views/admin/theme-settings.php', ['theme' => $theme, 'item' => $item, 'fields' => $this->settings->fields($theme), 'values' => $values, 'errors' => $errors, 'formErrors' => $formErrors, 'csrfToken' => $this->app->csrf()->token(), 'inventoryPath' => $this->app->adminUrl()->childUrl('themes'), 'formPath' => $this->app->adminUrl()->childUrl('themes/' . $id . '/settings'), 'resetPath' => $this->app->adminUrl()->childUrl('themes/' . $id . '/settings/reset'), 'saved' => $request->input('saved') === '1', 'reset' => $request->input('reset') === '1']);
        return Response::html($this->app->adminPageRenderer()->render('Theme settings', $content, $user, $this->app->csrf()->token(), $request->path()), $status);
    }
    private function view(string $file, array $data): string { extract($data, EXTR_SKIP); ob_start(); require $file; return (string) ob_get_clean(); }
}
