<?php
$descriptionId = $description !== null ? $titleId . '-description' : null;
?>
<section class="admin-page-frame admin-page-frame--surface-<?= htmlspecialchars($surface, ENT_QUOTES, 'UTF-8') ?> admin-page-frame--spacing-<?= htmlspecialchars($spacing, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>"<?= $descriptionId !== null ? ' aria-describedby="' . htmlspecialchars($descriptionId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <header class="admin-page-frame__header admin-page-heading">
        <div class="admin-page-frame__heading admin-page-heading__copy">
            <h2 class="admin-page-frame__title admin-page-heading__title" id="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if ($description !== null): ?><p class="admin-page-frame__description admin-page-heading__description" id="<?= htmlspecialchars($descriptionId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </div>
        <?php if ($bar !== null): ?><div class="admin-page-frame__bar admin-page-heading__actions"><?= $bar ?></div><?php endif; ?>
    </header>
    <div class="admin-page-frame__content"><?= $content ?></div>
    <?php if ($footer !== null): ?><footer class="admin-page-frame__footer"><?= $footer ?></footer><?php endif; ?>
</section>
