<?php
$typeLabels = [
    'page' => 'Page',
    'article' => 'Article',
];
$statusLabels = [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived',
];
$totalResults = (int) ($total ?? 0);
$currentPage = max(1, (int) ($page ?? 1));
$pageSize = max(1, (int) ($perPage ?? 25));
$firstResult = $totalResults > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$lastResult = $totalResults > 0 ? min($currentPage * $pageSize, $totalResults) : 0;
$paginationPages = [];
$lastPageNumber = max(1, (int) ($lastPage ?? 1));

if ($lastPageNumber <= 7) {
    $paginationPages = range(1, $lastPageNumber);
} else {
    $paginationPages[] = 1;

    if ($currentPage > 3) {
        $paginationPages[] = 'ellipsis-before';
    }

    foreach (range(max(2, $currentPage - 1), min($lastPageNumber - 1, $currentPage + 1)) as $paginationPage) {
        $paginationPages[] = $paginationPage;
    }

    if ($currentPage < $lastPageNumber - 2) {
        $paginationPages[] = 'ellipsis-after';
    }

    $paginationPages[] = $lastPageNumber;
}
?>
<section class="admin-content-page" aria-labelledby="content-list-title">
    <div class="admin-content-layout">
        <header class="admin-content-header">
            <div class="admin-content-header__copy">
                <p class="admin-content-eyebrow">Content workspace</p>
                <h2 id="content-list-title">Content</h2>
                <p>Manage pages, articles, publishing status, and taxonomy assignments.</p>
            </div>
            <?php if (!empty($canCreate)): ?>
                <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('content/create'), ENT_QUOTES, 'UTF-8') ?>">Create content</a>
            <?php endif; ?>
        </header>

        <form class="admin-panel admin-content-filters" method="get" action="<?= htmlspecialchars($adminUrl('content'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Content filters">
            <div class="admin-content-filter-field admin-content-search">
                <label for="content-search">Search</label>
                <input id="content-search" type="search" name="q" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Search title or slug">
            </div>

            <div class="admin-content-filter-field">
                <label for="content-type">Type</label>
                <select id="content-type" name="type">
                    <option value="">All types</option>
                    <option value="page" <?= (($selectedType ?? null) === 'page') ? 'selected' : '' ?>>Page</option>
                    <option value="article" <?= (($selectedType ?? null) === 'article') ? 'selected' : '' ?>>Article</option>
                </select>
            </div>

            <div class="admin-content-filter-field">
                <label for="content-status">Status</label>
                <select id="content-status" name="status">
                    <option value="">All statuses</option>
                    <option value="draft" <?= (($selectedStatus ?? null) === 'draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= (($selectedStatus ?? null) === 'published') ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= (($selectedStatus ?? null) === 'archived') ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <div class="admin-content-filter-field">
                <label for="content-per-page">Per page</label>
                <select id="content-per-page" name="per_page">
                    <?php foreach ([25, 50, 100] as $pageSizeOption): ?>
                        <option value="<?= $pageSizeOption ?>" <?= (($perPage ?? 25) === $pageSizeOption) ? 'selected' : '' ?>><?= $pageSizeOption ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-content-filter-actions">
                <button class="admin-button admin-button--secondary" type="submit">Apply filters</button>
                <?php if (!empty($hasFilters)): ?>
                    <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('content'), ENT_QUOTES, 'UTF-8') ?>">Clear filters</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($hasFilters)): ?>
            <p class="admin-content-filter-summary" role="status">
                Active filters:
                <?php if (($search ?? '') !== ''): ?><strong>search “<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>”</strong><?php endif; ?>
                <?php if (($selectedType ?? null) !== null): ?><strong><?= htmlspecialchars($typeLabels[$selectedType] ?? $selectedType, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
                <?php if (($selectedStatus ?? null) !== null): ?><strong><?= htmlspecialchars($statusLabels[$selectedStatus] ?? $selectedStatus, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (empty($contents)): ?>
            <div class="admin-empty-state admin-content-empty-state">
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
            <section class="admin-panel admin-content-table-panel" aria-labelledby="content-entries-title">
                <header class="admin-content-table-panel__header">
                    <div>
                        <h3 id="content-entries-title">Content entries</h3>
                        <p>Showing <?= $firstResult ?>–<?= $lastResult ?> of <?= $totalResults ?> results.</p>
                    </div>
                </header>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Type</th>
                                <th class="admin-content-slug-column" scope="col">Slug</th>
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
                                $status = $item->status();
                                $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                                $typeLabel = $typeLabels[$item->type()] ?? ucfirst($item->type());
                                $statusBadgeClass = match ($status) {
                                    'published' => 'admin-badge--success',
                                    'draft' => 'admin-badge--warning',
                                    'archived' => 'admin-badge--info',
                                    default => '',
                                };
                                $title = $item->title();
                                ?>
                                <tr>
                                    <td data-label="Title">
                                        <div class="admin-content-title-cell">
                                            <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small class="<?= $status === 'published' ? 'is-published' : '' ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Type"><span class="admin-content-type-badge"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="admin-content-slug-cell admin-content-slug-column" data-label="Slug"><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($item->slug(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <?php if (!empty($taxonomyAvailable)): ?>
                                        <td data-label="Taxonomy">
                                            <div class="admin-content-taxonomy">
                                                <?php if ($categoryNames !== []): ?><span><strong>Categories:</strong> <?= htmlspecialchars(implode(', ', $categoryNames), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                                <?php if ($tagNames !== []): ?><span><strong>Tags:</strong> <?= htmlspecialchars(implode(', ', $tagNames), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                                <?php if ($categoryNames === [] && $tagNames === []): ?><span class="admin-text-muted">None</span><?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td data-label="Status"><span class="admin-badge <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Updated"><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($item->updatedAt(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Actions">
                                        <div class="admin-row-actions">
                                            <?php if (!empty($canUpdate)): ?>
                                                <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Edit <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                                            <?php endif; ?>
                                            <?php if (!empty($canPublish) && $status === 'draft'): ?>
                                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/publish'), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="admin-button admin-button--link" type="submit" aria-label="Publish <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">Publish</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (!empty($canPublish) && $status === 'published'): ?>
                                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/draft'), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="admin-button admin-button--link" type="submit" aria-label="Move <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> to draft">Draft</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (!empty($canDelete) && $item->isArchived()): ?>
                                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/restore'), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="admin-button admin-button--link" type="submit" aria-label="Restore <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">Restore</button>
                                                </form>
                                            <?php elseif (!empty($canDelete)): ?>
                                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/archive'), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="admin-button admin-button--link admin-action-danger" type="submit" aria-label="Archive <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">Archive</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($lastPageNumber > 1): ?>
                    <nav class="admin-content-pagination" aria-label="Content pagination">
                        <span>Page <?= $currentPage ?> of <?= $lastPageNumber ?></span>
                        <div class="admin-content-pagination__pages">
                            <?php if ($currentPage > 1): ?>
                                <a class="admin-button admin-button--link" href="<?= htmlspecialchars($paginationUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page">Previous</a>
                            <?php endif; ?>
                            <?php foreach ($paginationPages as $paginationPage): ?>
                                <?php if (is_string($paginationPage)): ?>
                                    <span class="admin-content-pagination__ellipsis" aria-hidden="true">…</span>
                                <?php elseif ($paginationPage === $currentPage): ?>
                                    <span class="is-active" aria-current="page"><?= $paginationPage ?></span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($paginationUrl($paginationPage), ENT_QUOTES, 'UTF-8') ?>"><?= $paginationPage ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($currentPage < $lastPageNumber): ?>
                                <a class="admin-button admin-button--link" href="<?= htmlspecialchars($paginationUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page">Next</a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
