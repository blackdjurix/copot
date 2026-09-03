<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$media = is_array($media ?? null) ? $media : [];
$areas = ['identity' => 'Site Identity', 'system' => 'System', 'security' => 'Security', 'email' => 'Email', 'modules' => 'Modules', 'health' => 'System Health'];
$siteAssets = $siteAssets ?? null;
?>
<section class="admin-panel site-settings-page" data-site-settings>
    <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title">Site Settings</h2><p class="admin-panel__description">Configure the site identity and baseline appearance.</p></div></header>
    <nav class="admin-settings-tabs" aria-label="Site Settings areas">
        <?php foreach ($areas as $id => $label): ?><a class="admin-settings-tab<?= $id === 'identity' ? ' is-active' : '' ?>" href="#site-settings-<?= $id ?>"><?= $escape($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="admin-panel__body">
        <?php if (($notice ?? null) !== null): ?><div class="admin-alert admin-alert--success" role="status"><?= $escape($notice) ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="admin-alert admin-alert--danger" role="alert">Some Site Settings could not be saved.</div><?php endif; ?>
        <form method="post" action="<?= $escape($path) ?>" class="admin-form" id="site-settings-identity">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">General</legend>
                <?php foreach ([['site_name','Site Name','name'],['site_tagline','Site Tagline','tagline']] as [$name,$label,$key]): ?><div class="admin-field"><label class="admin-field__label" for="<?= $name ?>"><?= $escape($label) ?></label><input id="<?= $name ?>" name="<?= $name ?>" type="text" value="<?= $escape($values[$key] ?? '') ?>" <?= $key === 'name' ? 'required' : '' ?>><p class="admin-field__error"><?= $escape($errors['site.' . $key] ?? '') ?></p></div><?php endforeach; ?>
            </fieldset>
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Homepage</legend><label class="admin-field__label" for="hero_media">Hero Image</label><select id="hero_media" name="hero_media"><option value="">No Hero Image</option><?php foreach ($media as $item): ?><option value="<?= $escape($item->id()->value()) ?>" <?= (int) ($values['hero_media'] ?? 0) === $item->id()->value() ? 'selected' : '' ?>><?= $escape($item->title()) ?> — <?= $escape($item->originalFilename()) ?></option><?php endforeach; ?></select><p class="admin-field__help">Selected through Core Media; referenced media cannot be deleted.</p><p class="admin-field__error"><?= $escape($errors['hero_media'] ?? '') ?></p></fieldset>
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Localization</legend><?php foreach ([['locale','Locale'],['timezone','Timezone'],['date_format','Date Format'],['time_format','Time Format']] as [$key,$label]): ?><div class="admin-field"><label class="admin-field__label" for="<?= $key ?>"><?= $escape($label) ?></label><input id="<?= $key ?>" name="<?= $key ?>" type="text" value="<?= $escape($values[$key] ?? '') ?>" required></div><?php endforeach; ?></fieldset>
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Appearance</legend><label class="admin-field__label" for="main_color">Webcore Color Scheme — Main Color</label><input id="main_color" name="main_color" type="color" value="<?= $escape($values['main_color'] ?? '#1769e0') ?>" required><p class="admin-field__help">System-derived shades and Webcore-owned black/white neutral bases are generated automatically.</p></fieldset>
            <button class="admin-button admin-button--primary" type="submit">Save Site Settings</button>
        </form>
        <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Site Assets</legend>
            <?php foreach ([['logo','Logo','logoUploadAction','logoRemoveAction'],['favicon','Favicon','faviconUploadAction','faviconRemoveAction']] as [$slot,$label,$uploadAction,$removeAction]): ?>
                <div class="admin-field"><span class="admin-field__label"><?= $escape($label) ?></span><?php if ($siteAssets?->url($slot)): ?><img src="<?= $escape($siteAssets->url($slot)) ?>" alt="Current <?= $escape(strtolower($label)) ?>" style="max-width:180px;max-height:64px;display:block;margin:.5rem 0"><?php endif; ?><form method="post" action="<?= $escape($$uploadAction) ?>" enctype="multipart/form-data"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="file" name="site_asset" accept="image/png,image/jpeg,image/webp,image/x-icon" required><button class="admin-button admin-button--secondary" type="submit">Upload <?= $escape($label) ?></button></form><?php if ($siteAssets?->url($slot)): ?><form method="post" action="<?= $escape($$removeAction) ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><button class="admin-button admin-button--secondary" type="submit">Remove <?= $escape($label) ?></button></form><?php endif; ?></div>
            <?php endforeach; ?>
        </fieldset>
        <?php foreach (['system' => 'System settings are available in a later WU4 batch.', 'security' => 'Security settings are not configurable in this build.', 'email' => 'Email settings are not configurable in this build.', 'modules' => 'Module operations are available in a later WU4 batch.', 'health' => 'System Health presentation is available in a later WU4 batch.'] as $id => $message): ?><section class="admin-settings-panel" id="site-settings-<?= $id ?>"><h3><?= $escape($areas[$id]) ?></h3><div class="admin-empty-state"><h4>Not configurable in Batch 1</h4><p><?= $escape($message) ?></p></div></section><?php endforeach; ?>
    </div>
</section>
