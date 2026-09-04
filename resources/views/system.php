<?php
$heading = is_string($heading ?? null) && trim($heading) !== '' ? $heading : 'System Page';
$message = is_string($message ?? null) && trim($message) !== '' ? $message : 'This system page is controlled by the current site state.';
?>
<section class="builtin-site-system" aria-labelledby="builtin-system-title">
    <h1 id="builtin-system-title"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
</section>
