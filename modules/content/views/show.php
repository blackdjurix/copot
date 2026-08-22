<?php
$entry = $context['content'] ?? null;
$featuredMedia = $context['featuredMedia'] ?? null;
?>

<?php if (is_array($entry)): ?>
    <article>
        <h1><?= htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if (is_array($featuredMedia ?? null)): ?>
            <img src="<?= htmlspecialchars((string) $featuredMedia['url'], ENT_QUOTES, 'UTF-8') ?>"<?= !empty($featuredMedia['srcset']) ? ' srcset="' . htmlspecialchars((string) $featuredMedia['srcset'], ENT_QUOTES, 'UTF-8') . '" sizes="(max-width: 720px) 100vw, 720px"' : '' ?> width="<?= (int) ($featuredMedia['width'] ?? 0) ?>" height="<?= (int) ($featuredMedia['height'] ?? 0) ?>" alt="<?= htmlspecialchars((string) ($featuredMedia['alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="eager" style="display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover">
        <?php endif; ?>

        <?php if (($entry['excerpt'] ?? null) !== null): ?>
            <p><?= nl2br(htmlspecialchars((string) $entry['excerpt'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <div>
            <?= nl2br(htmlspecialchars((string) ($entry['body'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </article>
<?php endif; ?>
