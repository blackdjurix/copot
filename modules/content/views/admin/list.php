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
        <form class="admin-filters" method="get" action="<?= htmlspecialchars($adminUrl('content'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-filters__row">
                <label class="admin-field">
                    <span class="admin-field__label">Search</span>
                    <input type="search" name="q" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Title or slug">
                </label>
                <label class="admin-field">
                    <span class="admin-field__label">Type</span>
                    <select name="type">
                        <option value="">All types</option>
                        <option value="page" <?= (($selectedType ?? null) === 'page') ? 'selected' : '' ?>>Page</option>
                        <option value="article" <?= (($selectedType ?? null) === 'article') ? 'selected' : '' ?>>Article</option>
                    </select>
                </label>
                <label class="admin-field">
                    <span class="admin-field__label">Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        <option value="draft" <?= (($selectedStatus ?? null) === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= (($selectedStatus ?? null) === 'published') ? 'selected' : '' ?>>Published</option>
                        <option value="archived" <?= (($selectedStatus ?? null) === 'archived') ? 'selected' : '' ?>>Archived</option>
                    </select>
                </label>
                <label class="admin-field">
                    <span class="admin-field__label">Per page</span>
                    <select name="per_page">
                        <?php foreach ([25, 50, 100] as $pageSize): ?>
                            <option value="<?= $pageSize ?>" <?= (($perPage ?? 25) === $pageSize) ? 'selected' : '' ?>><?= $pageSize ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="admin-filters__actions">
                    <button class="admin-button admin-button--secondary" type="submit">Apply</button>
                    <?php if (!empty($hasFilters)): ?>
                        <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('content'), ENT_QUOTES, 'UTF-8') ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if (($total ?? 0) > 0): ?>
            <p class="admin-table-meta">Showing page <?= (int) ($page ?? 1) ?> of <?= (int) ($lastPage ?? 1) ?> (<?= (int) $total ?> results)</p>
        <?php endif; ?>

        <?php if (empty($contents)): ?>
            <div class="admin-empty-state">
                <?php if (!empty($hasFilters)): ?>
                    <h3 class="admin-empty-state__title">No matching content</h3>
                    <p class="admin-empty-state__description">No content matches the current search or filters.</p>
                    <div class="admin-empty-state__actions">
                        <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('content'), ENT_QUOTES, 'UTF-8') ?>">Clear filters</a>
                    </div>
                <?php else: ?>
                    <h3 class="admin-empty-state__title">No content yet</h3>
                    <p class="admin-empty-state__description">Create the first content entry to begin publishing.</p>

                    <?php if (!empty($canCreate)): ?>
                        <div class="admin-empty-state__actions">
                            <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('content/create'), ENT_QUOTES, 'UTF-8') ?>">Create content</a>
                        </div>
                    <?php endif; ?>
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
                            $statusBadgeClass = match ($item->status()) {
                                'published' => 'admin-badge--success',
                                'draft' => 'admin-badge--warning',
                                'archived' => 'admin-badge--info',
                                default => '',
                            };
                            ?>
                            <tr>
                                <td><strong class="admin-table-primary"><?= htmlspecialchars($item->title(), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><span class="admin-table-meta"><?= htmlspecialchars($item->type(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($item->slug(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <?php if (!empty($taxonomyAvailable)): ?>
                                    <td>
                                        <div class="admin-content-taxonomy">
                                            <?php if ($categoryNames !== []): ?>
                                                <span><strong>Categories:</strong> <?= htmlspecialchars(implode(', ', $categoryNames), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>

                                            <?php if ($tagNames !== []): ?>
                                                <span><strong>Tags:</strong> <?= htmlspecialchars(implode(', ', $tagNames), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>

                                            <?php if ($categoryNames === [] && $tagNames === []): ?>
                                                <span class="admin-text-muted">None</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td><span class="admin-badge <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->status(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($item->updatedAt(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <div class="admin-row-actions">
                                        <?php if (!empty($canUpdate)): ?>
                                            <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                                        <?php endif; ?>

                                        <?php if (!empty($canPublish) && $item->status() === 'draft'): ?>
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

                                        <?php if (!empty($canDelete) && $item->isArchived()): ?>
                                            <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/restore'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="admin-button admin-button--link" type="submit">Restore</button>
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
            <?php if (($lastPage ?? 1) > 1): ?>
                <nav class="admin-pagination" aria-label="Content pagination">
                    <?php if (($page ?? 1) > 1): ?>
                        <a class="admin-button admin-button--link" href="<?= htmlspecialchars($paginationUrl(($page ?? 1) - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                    <?php endif; ?>
                    <span class="admin-table-meta">Page <?= (int) $page ?> of <?= (int) $lastPage ?></span>
                    <?php if (($page ?? 1) < ($lastPage ?? 1)): ?>
                        <a class="admin-button admin-button--link" href="<?= htmlspecialchars($paginationUrl(($page ?? 1) + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
