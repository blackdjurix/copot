<?php

namespace Copot\Core;

use Throwable;

final class ServerErrorResponse
{
    public const INTERNAL_SERVER_ERROR = 500;
    public const SERVICE_UNAVAILABLE = 503;

    public static function fromThrowable(
        Throwable $exception,
        Diagnostics $diagnostics,
        string $event,
        array $context = [],
        int $status = self::INTERNAL_SERVER_ERROR
    ): Response {
        $status = $status === self::SERVICE_UNAVAILABLE
            ? self::SERVICE_UNAVAILABLE
            : self::INTERNAL_SERVER_ERROR;
        $context['status'] = $status;
        $reference = $diagnostics->report($event, $exception, $context);

        return self::response($status, $reference);
    }

    public static function response(int $status, ?string $reference = null): Response
    {
        $status = $status === self::SERVICE_UNAVAILABLE
            ? self::SERVICE_UNAVAILABLE
            : self::INTERNAL_SERVER_ERROR;
        $title = $status === self::SERVICE_UNAVAILABLE
            ? 'Service Unavailable'
            : 'Server Error';
        $message = $status === self::SERVICE_UNAVAILABLE
            ? 'The service is temporarily unavailable.'
            : 'The request could not be completed.';
        $referenceMarkup = '';

        if (is_string($reference) && preg_match('/^ERR-[A-F0-9]{24}$/', $reference) === 1) {
            $referenceMarkup = '<p>Error reference: <code>'
                . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8')
                . '</code></p>';
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title></head><body><main>'
            . '<h1>' . $title . '</h1><p>' . $message . '</p>'
            . $referenceMarkup
            . '</main></body></html>';

        return Response::content($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
