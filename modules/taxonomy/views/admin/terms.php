<section class="admin-panel" aria-labelledby="taxonomy-terms-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="taxonomy-terms-title">
                <?= htmlspecialchars($type?->name() ?? 'Taxonomy', ENT_QUOTES, 'UTF-8') ?> terms
            </h2>
            <p class="admin-panel__description">Manage reusable <?= htmlspecialchars($type?->slug() ?? 'taxonomy', ENT_QUOTES, 'UTF-8') ?> terms.</p>
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
                            $usageCountsForTerms = isset($usageCounts) && is_array($usageCounts) ? $usageCounts : [];
                            $usageAvailable = array_key_exists($term->id(), $usageCountsForTerms);
                            $usageCount = $usageAvailable ? (int) $usageCountsForTerms[$term->id()] : null;
                            ?>
                            <tr>
                                <td><strong class="admin-table-primary"><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></strong></td>
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

                                        <?php if (!empty($canDelete) && $usageAvailable && $usageCount === 0): ?>
                                            <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/' . $term->id() . '/delete'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="admin-button admin-button--link admin-action-danger" type="submit">Delete</button>
                                            </form>
                                        <?php elseif (!empty($canDelete) && $usageAvailable && $usageCount > 0): ?>
                                            <span class="admin-badge admin-badge--warning">In use</span>
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
