<section class="admin-panel" aria-labelledby="content-list-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="content-list-title">Content entries</h2>
            <p class="admin-panel__description">Manage pages, articles, publishing status, and taxonomy assignments.</p>
        </div>

        <?php if (!empty($canCreate)): ?>
            <div class="admin-actions">
                <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('content/create'), ENT_QUOTES, 'UTF-8') ?>">Create content</a>
            </div>
        <?php endif; ?>
    </header>

    <div class="admin-panel__body">
        <?php if (empty($contents)): ?>
            <div class="admin-empty-state">
                <h3 class="admin-empty-state__title">No content yet</h3>
                <p class="admin-empty-state__description">Create the first content entry to begin publishing.</p>

                <?php if (!empty($canCreate)): ?>
                    <div class="admin-empty-state__actions">
                        <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('content/create'), ENT_QUOTES, 'UTF-8') ?>">Create content</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Type</th>
                            <th scope="col">Slug</th>
                            <?php if (!empty($taxonomyAvailable)): ?>
                                <th scope="col">Taxonomy</th>
                            <?php endif; ?>
                            <th scope="col">Status</th>
                            <th scope="col">Updated</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($contents ?? []) as $item): ?>
                            <?php
                            $assigned = ($taxonomyTerms ?? [])[$item->id()] ?? ['categories' => [], 'tags' => []];
                            $categoryNames = array_map(fn ($term): string => $term->name(), $assigned['categories'] ?? []);
                            $tagNames = array_map(fn ($term): string => $term->name(), $assigned['tags'] ?? []);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($item->title(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($item->type(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($item->slug(), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php if (!empty($taxonomyAvailable)): ?>
                                    <td>
                                        <?php if ($categoryNames !== []): ?>
                                            <strong>Categories:</strong> <?= htmlspecialchars(implode(', ', $categoryNames), ENT_QUOTES, 'UTF-8') ?><br>
                                        <?php endif; ?>

                                        <?php if ($tagNames !== []): ?>
                                            <strong>Tags:</strong> <?= htmlspecialchars(implode(', ', $tagNames), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>

                                        <?php if ($categoryNames === [] && $tagNames === []): ?>
                                            <span class="admin-text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($item->status(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($item->updatedAt(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div class="admin-row-actions">
                                        <?php if (!empty($canUpdate)): ?>
                                            <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                                        <?php endif; ?>

                                        <?php if (!empty($canPublish) && $item->status() !== 'published'): ?>
                                            <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/publish'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="admin-button admin-button--link" type="submit">Publish</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!empty($canPublish) && $item->status() === 'published'): ?>
                                            <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/draft'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="admin-button admin-button--link" type="submit">Draft</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!empty($canDelete) && !$item->isArchived()): ?>
                                            <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/archive'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="admin-button admin-button--link admin-action-danger" type="submit">Archive</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
