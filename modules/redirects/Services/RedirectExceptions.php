<?php

require_once __DIR__ . '/../../../app/Core/RedirectExceptions.php';

// Compatibility names for older module-hosted consumers. Exception authority
// remains in the Core Redirect stack.
if (!class_exists('RedirectStaleWriteException', false)) {
    class_alias(\Copot\Core\RedirectStaleWriteException::class, 'RedirectStaleWriteException');
}
if (!class_exists('RedirectNotFoundException', false)) {
    class_alias(\Copot\Core\RedirectNotFoundException::class, 'RedirectNotFoundException');
}
