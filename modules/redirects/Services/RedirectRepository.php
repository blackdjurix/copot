<?php

// Compatibility name for the historical module-hosted operator surface.
// Redirect persistence is exclusively owned by Copot\Core\RedirectRepository.
if (!class_exists('RedirectRepository', false)) {
    class_alias(\Copot\Core\RedirectRepository::class, 'RedirectRepository');
}
