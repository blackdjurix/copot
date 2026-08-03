<?php
$data = is_array($context ?? null) ? $context : [];
$form = $data['form'] ?? null;
$fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
$values = is_array($data['values'] ?? null) ? $data['values'] : [];
$errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
$esc = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$action = $esc($data['action'] ?? '');
?>
<?php if ($form instanceof Form): ?>
<section class="form-manager-public" aria-labelledby="form-manager-title">
    <h1 id="form-manager-title"><?= $esc($form->name()) ?></h1>
    <?php if (!empty($data['submitted'])): ?><div role="status">Thank you. Your submission has been received.</div><?php endif; ?>
    <?php if ($errors !== []): ?>
        <div role="alert" tabindex="-1" aria-labelledby="form-manager-errors-title">
            <h2 id="form-manager-errors-title">Please correct the highlighted fields.</h2>
            <ul><?php foreach ($errors as $error): ?><li><?= $esc($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if (empty($data['submitted'])): ?>
    <form method="post" action="<?= $action ?>">
        <input type="hidden" name="_token" value="<?= $esc($data['csrfToken'] ?? '') ?>">
        <input type="hidden" name="_form_nonce" value="<?= $esc($data['nonce'] ?? '') ?>">
        <div aria-hidden="true" hidden><label for="form-manager-website">Website</label><input id="form-manager-website" name="_form_website" value="" tabindex="-1" autocomplete="off"></div>
        <?php foreach ($fields as $field): $key = $field->key(); $id = 'form-field-' . $field->id()->value(); $fieldError = $data['fieldErrors'][$key] ?? null; $value = $values[$key] ?? ''; $described = $fieldError !== null ? ' aria-describedby="' . $esc($id . '-error') . '"' : ''; ?>
            <div>
                <label for="<?= $esc($id) ?>"><?= $esc($field->label()) ?><?= $field->required() ? ' *' : '' ?></label>
                <?php if ($field->type() === 'textarea'): ?><textarea id="<?= $esc($id) ?>" name="values[<?= $esc($key) ?>]"<?= $field->required() ? ' required' : '' ?><?= $field->minLength() !== null ? ' minlength="' . (int) $field->minLength() . '"' : '' ?><?= $field->maxLength() !== null ? ' maxlength="' . (int) $field->maxLength() . '"' : '' ?><?= $fieldError !== null ? ' aria-invalid="true"' : '' ?><?= $described ?>><?= $esc($value) ?></textarea>
                <?php elseif ($field->type() === 'select'): ?><select id="<?= $esc($id) ?>" name="values[<?= $esc($key) ?>]"<?= $field->required() ? ' required' : '' ?><?= $fieldError !== null ? ' aria-invalid="true"' : '' ?><?= $described ?>><option value="">Select an option</option><?php foreach ($field->options() as $option): ?><option value="<?= $esc($option->value()) ?>"<?= (string) $value === $option->value() ? ' selected' : '' ?>><?= $esc($option->label()) ?></option><?php endforeach; ?></select>
                <?php elseif ($field->type() === 'checkbox'): ?><input id="<?= $esc($id) ?>" type="checkbox" name="values[<?= $esc($key) ?>]" value="1"<?= (string) $value === '1' ? ' checked' : '' ?><?= $field->required() ? ' required' : '' ?><?= $fieldError !== null ? ' aria-invalid="true"' : '' ?><?= $described ?>>
                <?php else: ?><input id="<?= $esc($id) ?>" type="<?= $field->type() === 'email' ? 'email' : 'text' ?>" name="values[<?= $esc($key) ?>]" value="<?= $esc($value) ?>"<?= $field->required() ? ' required' : '' ?><?= $field->minLength() !== null ? ' minlength="' . (int) $field->minLength() . '"' : '' ?><?= $field->maxLength() !== null ? ' maxlength="' . (int) $field->maxLength() . '"' : '' ?><?= $fieldError !== null ? ' aria-invalid="true"' : '' ?><?= $described ?>><?php endif; ?>
                <?php if ($fieldError !== null): ?><p id="<?= $esc($id . '-error') ?>" role="alert"><?= $esc($fieldError) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit">Submit</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
