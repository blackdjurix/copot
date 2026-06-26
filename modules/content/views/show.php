<?php
$entry = $context['content'] ?? null;
?>

<?php if ($entry instanceof Content): ?>
    <article>
        <h1><?= htmlspecialchars($entry->title(), ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($entry->excerpt() !== null): ?>
            <p><?= nl2br(htmlspecialchars($entry->excerpt(), ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <div>
            <?= nl2br(htmlspecialchars($entry->body(), ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </article>
<?php endif; ?>
