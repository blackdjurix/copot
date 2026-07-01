<section class="admin-panel" aria-labelledby="taxonomy-types-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="taxonomy-types-title">Taxonomy types</h2>
            <p class="admin-panel__description">Manage reusable classification terms.</p>
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
                            <th scope="col">Description</th>
                            <th scope="col">Hierarchy</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($types ?? []) as $type): ?>
                            <tr>
                                <td><?= htmlspecialchars($type->name(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($type->description() ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $type->isHierarchical() ? 'Prepared' : 'Flat' ?></td>
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
