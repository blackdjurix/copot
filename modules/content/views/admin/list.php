<section class="panel">
    <p>Manage content entries.</p>

    <?php if (!empty($canCreate)): ?>
        <p>
            <a href="<?= htmlspecialchars($adminUrl('content/create'), ENT_QUOTES, 'UTF-8') ?>">Create content</a>
        </p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Slug</th>
                <?php if (!empty($taxonomyAvailable)): ?>
                    <th>Taxonomy</th>
                <?php endif; ?>
                <th>Status</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contents)): ?>
                <tr>
                    <td colspan="<?= !empty($taxonomyAvailable) ? '7' : '6' ?>">No content yet.</td>
                </tr>
            <?php endif; ?>

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
                                Categories: <?= htmlspecialchars(implode(', ', $categoryNames), ENT_QUOTES, 'UTF-8') ?><br>
                            <?php endif; ?>

                            <?php if ($tagNames !== []): ?>
                                Tags: <?= htmlspecialchars(implode(', ', $tagNames), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($item->status(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item->updatedAt(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($canUpdate)): ?>
                            <a href="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                        <?php endif; ?>

                        <?php if (!empty($canPublish) && $item->status() !== 'published'): ?>
                            <form method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/publish'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Publish</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($canPublish) && $item->status() === 'published'): ?>
                            <form method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/draft'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Draft</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($canDelete) && !$item->isArchived()): ?>
                            <form method="post" action="<?= htmlspecialchars($adminUrl('content/' . $item->id() . '/archive'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
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
