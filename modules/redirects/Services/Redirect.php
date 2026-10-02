<?php

// Compatibility name for older module-hosted consumers. The Core Redirect
// entity is the only active domain implementation.
if (!class_exists('Redirect', false)) {
    class_alias(\Copot\Core\Redirect::class, 'Redirect');
}
