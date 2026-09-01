<?php
$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$selectedId = $selected?->id();
$renderTree = function (array $nodes) use (&$renderTree, $esc, $selectedId, $navigationPath, $itemPath, $csrfToken): void {
    foreach ($nodes as $node) {
        $item = $node['item']; $isSelected = $selectedId === $item->id();
        echo '<li class="admin-navigation-tree__node" style="--navigation-depth:' . (int) min(5, $node['depth']) . '">';
        echo '<div class="admin-navigation-tree__row ' . ($isSelected ? 'is-selected' : '') . '">';
        echo '<a class="admin-navigation-tree__select" href="' . $esc($navigationPath . '?item=' . $item->id()) . '" aria-current="' . ($isSelected ? 'page' : 'false') . '"><span class="admin-navigation-tree__branch" aria-hidden="true">' . ($node['children'] !== [] ? '▾' : '•') . '</span><span>' . $esc($item->label()) . '</span><small>' . $esc($item->targetKind() === 'custom' ? 'Custom URL' : ($item->targetKind() === 'content' ? 'Content' : 'Article Collection')) . '</small></a>';
        echo '</div>';
        if ($node['children'] !== []) { echo '<ol class="admin-navigation-tree">'; $renderTree($node['children']); echo '</ol>'; }
        echo '</li>';
    }
};
?>
<div class="admin-navigation-workspace" data-navigation-workspace>
    <section class="admin-panel admin-navigation-workspace__master" aria-labelledby="navigation-tree-title">
        <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="navigation-tree-title">Primary Navigation</h2><p class="admin-panel__description">Select an item to view or edit it.</p></div><a class="admin-button admin-button--primary" href="<?= $esc($navigationPath . '?create=1') ?>">Add item</a></header>
        <div class="admin-panel__body">
            <?php if ($tree === []): ?><div class="admin-empty-state"><h3 class="admin-empty-state__title">No navigation items yet</h3><p class="admin-empty-state__description">Add the first Primary Navigation item.</p></div><?php else: ?><nav aria-label="Primary Navigation hierarchy"><ol class="admin-navigation-tree"><?php $renderTree($tree); ?></ol></nav><?php endif; ?>
        </div>
    </section>
    <section class="admin-panel admin-navigation-workspace__detail" aria-labelledby="navigation-detail-title">
        <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="navigation-detail-title"><?= $creating ? 'Add navigation item' : ($selected && !$editing ? 'Navigation item preview' : ($selected ? 'Edit navigation item' : 'Navigation item')) ?></h2><p class="admin-panel__description"><?= $selected || $creating ? 'Configure this item without leaving the navigation workspace.' : 'Choose an item from the hierarchy, or add a new one.' ?></p></div></header>
        <div class="admin-panel__body">
            <?php if ($errors !== []): ?><div class="admin-alert admin-alert--danger" role="alert"><?php foreach ($errors as $group): foreach ((array) $group as $error): ?><p><?= $esc($error) ?></p><?php endforeach; endforeach; ?></div><?php endif; ?>
            <?php if ($selected && !$editing && !$creating): ?><dl class="admin-navigation-preview"><div><dt>Label</dt><dd><?= $esc($formItem['label']) ?></dd></div><div><dt>Target</dt><dd><?= $esc(ucwords(str_replace('_', ' ', $formItem['target_choice']))) ?></dd></div><div><dt>Visibility</dt><dd><?= !empty($formItem['is_visible']) ? 'Visible' : 'Hidden' ?></dd></div></dl><div class="admin-form__actions"><a class="admin-button admin-button--primary" href="<?= $esc($navigationPath . '?item=' . $selected->id() . '&edit=1') ?>">Edit</a><form method="post" action="<?= $esc($itemPath('/' . $selected->id() . '/delete')) ?>"><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><button class="admin-button admin-button--danger" type="submit">Delete</button></form></div><?php else: ?><form class="admin-form" method="post" action="<?= $esc($formAction) ?>" <?= !$selected && !$creating ? 'aria-disabled="true"' : '' ?>><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>">
                <?php if ($selected || $creating): ?>
                <div class="admin-field"><label class="admin-field__label" for="label">Label</label><input id="label" name="label" required value="<?= $esc($formItem['label'] ?? '') ?>" <?= !$selected && !$creating ? 'disabled' : '' ?>></div>
                <div class="admin-field"><label class="admin-field__label" for="parent_id">Parent item</label><select id="parent_id" name="parent_id" <?= !$selected && !$creating ? 'disabled' : '' ?>><option value="">Root item</option><?php foreach ($parents as $candidate): $candidateItem=$candidate['item']; ?><option value="<?= $candidateItem->id() ?>" <?= (string)($formItem['parent_id'] ?? '') === (string)$candidateItem->id() ? 'selected' : '' ?>><?= str_repeat('— ', (int)$candidate['depth']) . $esc($candidateItem->label()) ?></option><?php endforeach; ?></select></div>
                <fieldset class="admin-fieldset" <?= !$selected && !$creating ? 'disabled' : '' ?>><legend>Target</legend><div class="admin-field"><label class="admin-field__label" for="target_choice">Target</label><select id="target_choice" name="target_choice" data-navigation-target><option value="custom" <?= ($formItem['target_choice'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom URL</option><option value="content" <?= ($formItem['target_choice'] ?? '') === 'content' ? 'selected' : '' ?>>Content</option><option value="article_collection" <?= ($formItem['target_choice'] ?? '') === 'article_collection' ? 'selected' : '' ?>>Article Collection</option></select></div><div class="admin-field" data-navigation-target-panel="custom"><label class="admin-field__label" for="custom_url">URL</label><input id="custom_url" name="custom_url" value="<?= $esc($formItem['custom_url'] ?? '') ?>"></div><div class="admin-field" data-navigation-target-panel="content"><label class="admin-field__label" for="content_reference">Content</label><select id="content_reference" name="content_reference"><option value="">Select published Content</option><?php foreach ($contentOptions as $content): ?><option value="<?= $esc($content->slug()) ?>" <?= ($formItem['content_reference'] ?? '') === $content->slug() ? 'selected' : '' ?>><?= $esc($content->title()) ?> (<?= $esc(ucfirst($content->type())) ?>)</option><?php endforeach; ?></select></div><p class="admin-field__help" data-navigation-target-panel="article_collection">Uses the canonical published Articles collection at <code>/articles</code>.</p></fieldset>
                <label class="admin-check-option"><input type="checkbox" name="is_visible" value="1" <?= !empty($formItem['is_visible']) ? 'checked' : '' ?> <?= !$selected && !$creating ? 'disabled' : '' ?>> Visible</label>
                <?php endif; ?>
                <div class="admin-form__actions"><button class="admin-button admin-button--primary" type="submit" <?= !$selected && !$creating ? 'disabled' : '' ?>>Save</button></div>
            </form><?php endif; ?>
        </div>
    </section>
</div>
<script defer src="<?= $esc('/admin-assets/js/navigation-admin.js') ?>"></script>
