<h1>Articles</h1>
<?php if (($articles ?? []) === []): ?>
    <p>No published articles yet.</p>
<?php else: ?>
    <?php foreach ($articles as $article): ?>
        <article>
            <h2><a href="<?= htmlspecialchars('/content/' . (string) $article['slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $article['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
            <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?><p><?= htmlspecialchars((string) $article['excerpt'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
