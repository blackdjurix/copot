<section class="panel">
    <h2><?= htmlspecialchars($type?->name() ?? 'Taxonomy Terms', ENT_QUOTES, 'UTF-8') ?></h2>
    <p>Manage <?= htmlspecialchars($type?->slug() ?? 'taxonomy', ENT_QUOTES, 'UTF-8') ?> terms.</p>

    <?php if (!empty($error)): ?>
        <div>
            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>

    <p>
        <a href="<?= htmlspecialchars($adminUrl('taxonomy'), ENT_QUOTES, 'UTF-8') ?>">All taxonomy types</a>
        <?php if (!empty($canCreate)): ?>
            |
            <a href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/create'), ENT_QUOTES, 'UTF-8') ?>">Create term</a>
        <?php endif; ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Sort</th>
                <th>Usage</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($terms)): ?>
                <tr>
                    <td colspan="6">No terms yet.</td>
                </tr>
            <?php endif; ?>

            <?php foreach (($terms ?? []) as $term): ?>
                <?php $usageCount = (int) (($usageCounts ?? [])[$term->id()] ?? 0); ?>
                <tr>
                    <td><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($term->slug(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($term->description() ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $term->sortOrder(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $usageCount, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($canUpdate)): ?>
                            <a href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/' . $term->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                        <?php endif; ?>

                        <?php if (!empty($canDelete) && $usageCount === 0): ?>
                            <form method="post" action="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '') . '/' . $term->id() . '/delete'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Delete</button>
                            </form>
                        <?php elseif (!empty($canDelete)): ?>
                            <span>In use</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
