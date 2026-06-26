<section class="panel">
    <h2>Taxonomy</h2>
    <p>Manage reusable classification terms.</p>

    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Description</th>
                <th>Hierarchy</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($types)): ?>
                <tr>
                    <td colspan="4">No taxonomy types are available.</td>
                </tr>
            <?php endif; ?>

            <?php foreach (($types ?? []) as $type): ?>
                <tr>
                    <td><?= htmlspecialchars($type->name(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($type->description() ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $type->isHierarchical() ? 'Prepared' : 'Flat' ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($adminBase ?? '/admin', ENT_QUOTES, 'UTF-8') ?>/taxonomy/<?= htmlspecialchars($type->slug(), ENT_QUOTES, 'UTF-8') ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
