<?php

namespace Copot\Core\Admin;

use Copot\Core\Auth;
use Copot\Core\Csrf;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ServerErrorResponse;
use Copot\Core\View;
use Throwable;

final class AdminErrorRenderer
{
    private const ALLOWED_STATUSES = [403, 404, 419, 500, 503];

    public function __construct(
        private View $view,
        private AdminPageRenderer $pages,
        private AdminUrl $adminUrl,
        private Auth $auth,
        private Csrf $csrf,
        private string $basePermission
    ) {
        $this->basePermission = trim($this->basePermission);

        if ($this->basePermission === '') {
            throw new \InvalidArgumentException('Admin base permission cannot be empty.');
        }
    }

    public function response(Request $request, int $status, ?string $reference = null): Response
    {
        $status = in_array($status, self::ALLOWED_STATUSES, true) ? $status : 500;

        if (!$this->isAdminPath($request->path()) || session_status() !== PHP_SESSION_ACTIVE) {
            return $this->standalone($status, $reference);
        }

        $initialOutputLevel = ob_get_level();

        if (!@ob_start()) {
            return $this->standalone($status, $reference);
        }

        try {
            $user = $this->auth->user();

            if (!$user?->can($this->basePermission)) {
                $this->discardOutputBuffersTo($initialOutputLevel);

                return $this->standalone(
                    $status,
                    $reference,
                    $status === 403 && $user !== null ? $this->csrf->token() : null
                );
            }

            $contract = $this->contract($status);
            $content = $this->view->render('admin/error', [
                'heading' => $contract['heading'],
                'message' => $contract['message'],
                'reference' => $this->validReference($reference) ? $reference : null,
            ]);
            $html = $this->pages->render(
                $contract['title'],
                $content,
                $user,
                $this->csrf->token(),
                $request->path()
            );

            if (ob_get_level() !== $initialOutputLevel + 1) {
                throw new \RuntimeException('Admin error recovery output buffer state is invalid.');
            }

            $unexpectedOutput = @ob_get_clean();

            if (!is_string($unexpectedOutput) || $unexpectedOutput !== '') {
                throw new \RuntimeException('Admin error recovery emitted direct output.');
            }

            return Response::content($html, $status, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable) {
            $this->discardOutputBuffersTo($initialOutputLevel);

            return $this->standalone($status, $reference);
        }
    }

    private function isAdminPath(string $path): bool
    {
        $base = $this->adminUrl->baseUrl();

        return $path === $base || str_starts_with($path, $base . '/');
    }

    private function standalone(int $status, ?string $reference, ?string $logoutToken = null): Response
    {
        if ($status === 500 || $status === 503) {
            return ServerErrorResponse::response($status, $reference);
        }

        $contract = $this->contract($status);
        $logoutForm = '';

        if ($status === 403 && $logoutToken !== null) {
            $logoutForm = '<form method="post" action="'
                . htmlspecialchars($this->adminUrl->childUrl('logout'), ENT_QUOTES, 'UTF-8')
                . '"><input type="hidden" name="_token" value="'
                . htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8')
                . '"><button type="submit">Sign out</button></form>';
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars($contract['title'], ENT_QUOTES, 'UTF-8') . '</title>'
            . '</head><body><main><h1>'
            . htmlspecialchars($contract['heading'], ENT_QUOTES, 'UTF-8')
            . '</h1><p>'
            . htmlspecialchars($contract['message'], ENT_QUOTES, 'UTF-8')
            . '</p>' . $logoutForm . '</main></body></html>';

        return Response::content($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function contract(int $status): array
    {
        return match ($status) {
            403 => [
                'title' => 'Access Denied',
                'heading' => 'Access denied',
                'message' => 'You do not have permission to access this Admin resource.',
            ],
            404 => [
                'title' => 'Page Not Found',
                'heading' => 'Page not found',
                'message' => 'The requested Admin page or resource could not be found.',
            ],
            419 => [
                'title' => 'Request Verification Failed',
                'heading' => 'Request verification failed',
                'message' => 'The request could not be verified. Please return to the previous page and try again.',
            ],
            503 => [
                'title' => 'Service Unavailable',
                'heading' => 'Service unavailable',
                'message' => 'This Admin operation is temporarily unavailable.',
            ],
            default => [
                'title' => 'Server Error',
                'heading' => 'Server error',
                'message' => 'The request could not be completed.',
            ],
        };
    }

    private function validReference(?string $reference): bool
    {
        return is_string($reference) && preg_match('/^ERR-[A-F0-9]{24}$/', $reference) === 1;
    }

    private function discardOutputBuffersTo(int $initialLevel): void
    {
        while (ob_get_level() > $initialLevel) {
            $level = ob_get_level();

            if (!@ob_end_clean() || ob_get_level() >= $level) {
                break;
            }
        }
    }
}
