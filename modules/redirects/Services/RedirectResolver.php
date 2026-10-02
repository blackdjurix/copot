<?php

// Compatibility name for the historical module resolver contribution.
// Active unresolved-route resolution is exclusively owned by Core.
if (!class_exists('RedirectResolver', false)) {
    class_alias(\Copot\Core\RedirectResolver::class, 'RedirectResolver');
}
