<?php

$app->router()->get('/example', function () {
    $initialOutputLevel = ob_get_level();

    if (!@ob_start()) {
        throw new RuntimeException('Example view output buffer is unavailable.');
    }

    try {
        require __DIR__ . '/views/index.php';

        if (ob_get_level() !== $initialOutputLevel + 1) {
            throw new RuntimeException('Example view output buffer state is invalid.');
        }

        $rendered = @ob_get_clean();

        if (!is_string($rendered)) {
            throw new RuntimeException('Example view output buffer could not be read.');
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
});
