<?php

// Compatibility name for the historical module-hosted operator surface.
// Redirect business rules are exclusively owned by Copot\Core\RedirectService.
if (!class_exists('RedirectService', false)) {
    class_alias(\Copot\Core\RedirectService::class, 'RedirectService');
}
