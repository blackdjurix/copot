<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');

if (!is_string($bootstrap) || !is_string($view)) {
    throw new RuntimeException('Batch 4 installer sources could not be read.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($view, 'padding: 6px 8px;'), 'Shared installer controls do not use the accepted padding.');
$assert(str_contains($view, 'height: 34px;'), 'Shared installer controls lost their accepted height.');
$assert(!str_contains($view, 'padding: 8px 12px;'), 'Old shared control padding remains.');
$assert(!str_contains($view, 'padding: 10px 12px;'), 'Old phase form padding remains.');
$assert(str_contains($view, 'gap: 4px;'), 'Shared helper/error spacing was not tightened.');
$assert(!str_contains($view, 'margin-top: -8px;'), 'Negative helper/error margin remains.');
$assert(str_contains($view, 'Password is kept during this installation.'), 'Administrator password helper copy is incorrect.');
$passwordHelper = strpos($view, 'Password is kept during this installation.');
$passwordControl = strpos($view, 'id="admin_password"');
$confirmationControl = strpos($view, 'id="admin_password_confirmation"');
$assert($passwordHelper !== false && $passwordControl !== false && $confirmationControl !== false && $passwordHelper > $passwordControl && $passwordHelper < $confirmationControl, 'Administrator password helper is not scoped to the Password control.');
$assert(!str_contains($view, 'Password is retained securely'), 'Old Administrator helper copy remains.');
$nav = strpos($view, '</nav>');
$feedback = strpos($view, 'id="database_feedback"');
$databaseHeading = strpos($view, '<h2>Database</h2>');
$assert($nav !== false && $feedback > $nav && $databaseHeading > $feedback, 'Database feedback is not placed below progress and above the Database heading.');
$assert(str_contains($view, '.step-pending') && str_contains($view, 'color: #6b7280;'), 'Pending steps do not have neutral grey treatment.');
$assert(str_contains($bootstrap, "!in_array(\$step['label'], ['Requirements', 'Database'], true)"), 'Future blocked steps are not visually normalized to pending.');
$assert(str_contains($view, 'class="form-action-row"'), 'Namespace action row no longer uses shared primitives.');
$assert(str_contains($view, 'class="form-help">Blank preserves the empty namespace.</p>'), 'Namespace helper is missing.');

fwrite(STDOUT, "Batch 4 visual assertions: {$assertions}\n");
