<?php $articles = is_array($articles ?? null) ? $articles : []; $mediaUrl = $mediaUrl ?? null; ?>
<section class="builtin-site-article-collection" aria-labelledby="article-collection-title">
    <h1 id="article-collection-title">Articles</h1>
<?php if ($articles === []): ?>
    <p>No published articles yet.</p>
<?php else: ?>
    <div class="builtin-site-article-collection__items">
    <?php foreach ($articles as $article): ?>
                <?php $imageUrl = is_callable($mediaUrl) && !empty($article['featured_media_id']) ? $mediaUrl((int) $article['featured_media_id']) : null; ?>
        <article class="builtin-site-article-item<?= $imageUrl !== null ? ' has-image' : '' ?>">
            <?php if ($imageUrl !== null): ?><a class="builtin-site-article-item__image" href="<?= htmlspecialchars('/content/' . (string) $article['slug'], ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"></a><?php endif; ?>
            <div class="builtin-site-article-item__body">
                <h2><a href="<?= htmlspecialchars('/content/' . (string) $article['slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $article['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                <?php if (($article['published_at'] ?? null) !== null): ?><p class="builtin-site-article-item__meta">Published <?= htmlspecialchars((string) $article['published_at'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?><p><?= nl2br(htmlspecialchars((string) $article['excerpt'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
