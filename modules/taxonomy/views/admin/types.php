<section class="admin-panel" aria-labelledby="taxonomy-types-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="taxonomy-types-title">Taxonomy types</h2>
            <p class="admin-panel__description">Choose a built-in classification workspace.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (empty($types)): ?>
            <div class="admin-empty-state">
                <h3 class="admin-empty-state__title">No taxonomy types available</h3>
                <p class="admin-empty-state__description">No taxonomy type definitions are currently registered.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col">Purpose</th>
                            <th scope="col">Structure</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($types ?? []) as $type): ?>
                            <tr>
                                <td>
                                    <strong class="admin-table-primary"><?= htmlspecialchars($type->name(), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="admin-table-meta">Built-in type: <?= htmlspecialchars($type->slug(), ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <span class="admin-badge <?= $type->isHierarchical() ? 'admin-badge--info' : '' ?>"><?= $type->isHierarchical() ? 'Hierarchical' : 'Flat' ?></span>
                                    <span class="admin-table-meta">
                                        <?= $type->slug() === 'category' ? 'Organize content into parent and child groups.' : 'Apply independent labels without nesting.' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('taxonomy/' . $type->slug()), ENT_QUOTES, 'UTF-8') ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
