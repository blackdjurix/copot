<section class="panel">
    <h2>Content</h2>
    <p>Manage content entries.</p>

    <?php if (!empty($canCreate)): ?>
        <p>
            <a href="<?= htmlspecialchars($adminBase ?? '/admin', ENT_QUOTES, 'UTF-8') ?>/content/create">Create content</a>
        </p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contents)): ?>
                <tr>
                    <td colspan="6">No content yet.</td>
                </tr>
            <?php endif; ?>

            <?php foreach (($contents ?? []) as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item->title(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item->type(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item->slug(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item->status(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item->updatedAt(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($canUpdate)): ?>
                            <a href="<?= htmlspecialchars($adminBase ?? '/admin', ENT_QUOTES, 'UTF-8') ?>/content/<?= htmlspecialchars((string) $item->id(), ENT_QUOTES, 'UTF-8') ?>/edit">Edit</a>
                        <?php endif; ?>

                        <?php if (!empty($canPublish) && $item->status() !== 'published'): ?>
                            <form method="post" action="<?= htmlspecialchars($adminBase ?? '/admin', ENT_QUOTES, 'UTF-8') ?>/content/<?= htmlspecialchars((string) $item->id(), ENT_QUOTES, 'UTF-8') ?>/publish" style="display:inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Publish</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($canPublish) && $item->status() === 'published'): ?>
                            <form method="post" action="<?= htmlspecialchars($adminBase ?? '/admin', ENT_QUOTES, 'UTF-8') ?>/content/<?= htmlspecialchars((string) $item->id(), ENT_QUOTES, 'UTF-8') ?>/draft" style="display:inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Draft</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($canDelete) && !$item->isArchived()): ?>
                            <form method="post" action="<?= htmlspecialchars($adminBase ?? '/admin', ENT_QUOTES, 'UTF-8') ?>/content/<?= htmlspecialchars((string) $item->id(), ENT_QUOTES, 'UTF-8') ?>/archive" style="display:inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Archive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
