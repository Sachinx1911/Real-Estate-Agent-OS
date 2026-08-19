<?php
/**
 * RE360 — tiny CRUD helper for the data-entry forms.
 * Keeps insert/update logic in one place instead of repeating it per page.
 */

/**
 * Insert or update a row from $_POST.
 * @param string $table  table name (trusted, hard-coded by callers)
 * @param array  $fields whitelist of column names to take from $data
 * @param array  $data   usually $_POST
 * @param int|null $id   existing row id → update; null → insert
 * @return int the row id
 */
function save_row(string $table, array $fields, array $data, ?int $id = null): int
{
    $set = []; $vals = [];
    foreach ($fields as $f) {
        if (!array_key_exists($f, $data)) continue;
        $v = $data[$f];
        if (is_array($v)) $v = implode(',', $v);
        if ($v === '') $v = null;
        $set[] = "`$f` = ?";
        $vals[] = $v;
    }
    if (!$set) return (int)$id;

    if ($id) {
        $vals[] = $id;
        db()->prepare("UPDATE `$table` SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);
        return $id;
    }
    db()->prepare("INSERT INTO `$table` SET " . implode(', ', $set))->execute($vals);
    return (int) db()->lastInsertId();
}

/** Render a labelled text/number/date input */
function field(string $label, string $name, $value = '', string $type = 'text', array $attrs = []): string
{
    $a = '';
    foreach ($attrs as $k=>$v) $a .= ' ' . $k . '="' . e($v) . '"';
    return '<div class="form-group"><label>' . e($label) . '</label>'
         . '<input class="field-input" type="' . $type . '" name="' . e($name) . '" value="' . e($value) . '"' . $a . '></div>';
}

/** Render a labelled select */
function select_field(string $label, string $name, array $options, $selected = '', bool $allowEmpty = false): string
{
    $h = '<div class="form-group"><label>' . e($label) . '</label><select class="select" name="' . e($name) . '">';
    if ($allowEmpty) $h .= '<option value="">— Select —</option>';
    foreach ($options as $val => $lbl) {
        if (is_int($val)) $val = $lbl;                 // simple list
        $sel = ((string)$selected === (string)$val) ? ' selected' : '';
        $h .= '<option value="' . e($val) . '"' . $sel . '>' . e($lbl) . '</option>';
    }
    return $h . '</select></div>';
}

/** Render a labelled textarea */
function textarea_field(string $label, string $name, $value = '', string $placeholder = ''): string
{
    return '<div class="form-group full"><label>' . e($label) . '</label>'
         . '<textarea name="' . e($name) . '" placeholder="' . e($placeholder) . '">' . e($value) . '</textarea></div>';
}
