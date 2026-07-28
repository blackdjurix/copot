<section class="admin-panel" aria-labelledby="taxonomy-terms-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="taxonomy-terms-title">
                <?= htmlspecialchars($type?->name() ?? 'Taxonomy', ENT_QUOTES, 'UTF-8') ?> terms
            </h2>
            <p class="admin-panel__description">
                <?php if ($type?->slug() === 'category'): ?>
                    Categories are hierarchical and may contain child categories.
                <?php else: ?>
                    Tags are flat labels; they cannot contain or belong to a parent.
                <?php endif; ?>
            </p>
        </div>

        <div class="admin-actions">
            <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('taxonomy'), ENT_QUOTES, 'UTF-8') ?>">All taxonomy types</a>
            <?php if (!empty($canCreate)): ?>
                <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/create'), ENT_QUOTES, 'UTF-8') ?>">Create term</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (!empty($error)): ?>
            <div class="admin-alert admin-alert--danger" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (empty($terms)): ?>
            <div class="admin-empty-state">
                <h3 class="admin-empty-state__title">No terms yet</h3>
                <p class="admin-empty-state__description">Create the first term for this taxonomy type.</p>

                <?php if (!empty($canCreate)): ?>
                    <div class="admin-empty-state__actions">
                        <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/create'), ENT_QUOTES, 'UTF-8') ?>">Create term</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <?php if ($type?->isHierarchical()): ?><th scope="col">Hierarchy</th><?php endif; ?>
                            <th scope="col">Slug</th>
                            <th scope="col">Description</th>
                            <th scope="col">Sort</th>
                            <th scope="col">Usage</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($terms ?? []) as $term): ?>
                            <?php
                            $termParentId = method_exists($term, 'parentId') ? $term->parentId() : null;
                            $usageCountsForTerms = isset($usageCounts) && is_array($usageCounts) ? $usageCounts : [];
                            $usageAvailable = array_key_exists($term->id(), $usageCountsForTerms);
                            $usageCount = $usageAvailable ? (int) $usageCountsForTerms[$term->id()] : null;
                            ?>
                            <?php
                            $hasChildren = false;
                            foreach (($terms ?? []) as $candidate) {
                                if (method_exists($candidate, 'parentId') && $candidate->parentId() === $term->id()) {
                                    $hasChildren = true;
                                    break;
                                }
                            }
                            $parentName = null;
                            foreach (($terms ?? []) as $candidate) {
                                if ($candidate->id() === $termParentId) {
                                    $parentName = $candidate->name();
                                    break;
                                }
                            }
                            ?>
                            <tr>
                                <td><strong class="admin-table-primary"><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <?php if ($type?->isHierarchical()): ?>
                                    <td>
                                        <?php if ($parentName !== null): ?>
                                            <span class="admin-table-meta">Child of <?= htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge--info">Root category</span>
                                        <?php endif; ?>
                                        <?php if ($hasChildren): ?><span class="admin-badge admin-badge--warning">Has children</span><?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($term->slug(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($term->description() ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="admin-table-meta"><?= htmlspecialchars((string) $term->sortOrder(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <?php if ($usageAvailable): ?>
                                        <span class="admin-badge admin-badge--info"><?= htmlspecialchars((string) $usageCount, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="admin-badge">Usage unavailable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="admin-row-actions">
                                        <?php if (!empty($canUpdate)): ?>
                                            <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/' . $term->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                                        <?php endif; ?>

                                        <?php if (!empty($canDelete) && !$hasChildren && $usageAvailable && $usageCount === 0): ?>
                                            <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/' . $term->id() . '/delete'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="admin-button admin-button--link admin-action-danger" type="submit">Delete</button>
                                            </form>
                                        <?php elseif (!empty($canDelete) && $hasChildren): ?>
                                            <span class="admin-badge admin-badge--warning">Has children</span>
                                        <?php elseif (!empty($canDelete) && $usageAvailable && $usageCount > 0): ?>
                                            <span class="admin-badge admin-badge--warning">In use</span>
                                        <?php elseif (empty($canUpdate) && empty($canDelete)): ?>
                                            <span class="admin-table-meta">No actions available</span>
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
