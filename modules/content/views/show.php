<?php
$entry = $context['content'] ?? null;
$featuredMedia = $context['featuredMedia'] ?? null;
?>

<?php if (is_array($entry)): ?>
    <article>
        <?php if (is_array($breadcrumbs ?? null) && $breadcrumbs !== []): ?>
            <nav class="builtin-site-breadcrumb" aria-label="Breadcrumb">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <?php if ($index > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
                    <?php if (isset($crumb['url'])): ?><a href="<?= htmlspecialchars((string) $crumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a><?php else: ?><span aria-current="page"><?= htmlspecialchars((string) ($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <h1><?= htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if (is_array($featuredMedia ?? null)): ?>
            <img class="builtin-site-featured-image" src="<?= htmlspecialchars((string) $featuredMedia['url'], ENT_QUOTES, 'UTF-8') ?>"<?= !empty($featuredMedia['srcset']) ? ' srcset="' . htmlspecialchars((string) $featuredMedia['srcset'], ENT_QUOTES, 'UTF-8') . '" sizes="(max-width: 720px) 100vw, 720px"' : '' ?> width="<?= (int) ($featuredMedia['width'] ?? 0) ?>" height="<?= (int) ($featuredMedia['height'] ?? 0) ?>" alt="<?= htmlspecialchars((string) ($featuredMedia['alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="eager">
        <?php endif; ?>

        <?php if (($entry['excerpt'] ?? null) !== null): ?>
            <p class="builtin-site-page__intro"><?= nl2br(htmlspecialchars((string) $entry['excerpt'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <?php if (($entry['published_at'] ?? null) !== null): ?><p class="builtin-site-page__meta">Published <?= htmlspecialchars((string) $entry['published_at'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

        <div>
            <?= nl2br(htmlspecialchars((string) ($entry['body'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </article>
<?php endif; ?>
