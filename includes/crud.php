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

/** Render a labelled select
 *
 * Two shapes are accepted:
 *   ['1 BHK', '2 BHK']        plain list  → the label is also the value
 *   [7 => 'Sunrise Heights']  keyed map   → the key is the value
 *
 * The distinction has to be "is this a list?", not "is the key an integer".
 * Every id => name map built from the database (builders, projects, clients,
 * flats) has integer keys, so an is_int() test would post the *name* where a
 * foreign key id is expected and the insert would fail on the constraint.
 */
function select_field(string $label, string $name, array $options, $selected = '', bool $allowEmpty = false): string
{
    $isList = function_exists('array_is_list')
        ? array_is_list($options)
        : ($options === [] || array_keys($options) === range(0, count($options) - 1));

    $h = '<div class="form-group"><label>' . e($label) . '</label><select class="select" name="' . e($name) . '">';
    if ($allowEmpty) $h .= '<option value="">— Select —</option>';
    foreach ($options as $val => $lbl) {
        if ($isList) $val = $lbl;
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

/* ============================================================
   Deleting builders and projects
   ------------------------------------------------------------
   The schema cascades hard: removing a project takes its inventory,
   configurations, towers, pricing, payment plans, offers, amenity links,
   parking, legal docs and bookings with it. Removing a builder does that
   for every one of its projects.

   Two things the database will not do for us:
     - documents rows are linked by (entity_type, entity_id), not a foreign
       key, so they would survive as orphans pointing at nothing — along with
       the uploaded files on disk.
     - site_visits.project_id has no foreign key, so a visit would keep an id
       that no longer resolves. The visit belongs to the client, not the
       project, so the reference is cleared instead of deleting the record.
   ============================================================ */

/** Bookings recorded against a project — the reason a delete may be refused. */
function project_booking_count(int $projectId): int
{
    return (int) scalar("SELECT COUNT(*) FROM bookings WHERE project_id=?", [$projectId]);
}

/** Bookings across every project of a builder. */
function builder_booking_count(int $builderId): int
{
    return (int) scalar(
        "SELECT COUNT(*) FROM bookings b JOIN projects p ON p.id=b.project_id WHERE p.builder_id=?",
        [$builderId]
    );
}

/** What a project delete would take with it, for the confirmation message. */
function project_delete_summary(int $projectId): array
{
    return [
        'flats'    => (int) scalar("SELECT COUNT(*) FROM inventory WHERE project_id=?", [$projectId]),
        'configs'  => (int) scalar("SELECT COUNT(*) FROM project_configurations WHERE project_id=?", [$projectId]),
        'towers'   => (int) scalar("SELECT COUNT(*) FROM towers WHERE project_id=?", [$projectId]),
        'docs'     => (int) scalar("SELECT COUNT(*) FROM documents WHERE entity_type='project' AND entity_id=?", [$projectId]),
        'bookings' => project_booking_count($projectId),
    ];
}

/** Remove the things no foreign key covers, then let the cascade do the rest. */
function purge_project_extras(int $projectId): void
{
    require_once __DIR__ . '/upload.php';

    foreach (rows("SELECT file_path FROM documents WHERE entity_type='project' AND entity_id=?", [$projectId]) as $d) {
        delete_upload($d['file_path']);
    }
    db()->prepare("DELETE FROM documents WHERE entity_type='project' AND entity_id=?")->execute([$projectId]);

    $hero = scalar("SELECT hero_image FROM projects WHERE id=?", [$projectId]);
    if ($hero) delete_upload($hero);

    db()->prepare("UPDATE site_visits SET project_id=NULL WHERE project_id=?")->execute([$projectId]);
}

/**
 * Delete one project.
 * @return array{ok:bool, error?:string}
 */
function delete_project(int $projectId): array
{
    $p = row("SELECT id, name FROM projects WHERE id=?", [$projectId]);
    if (!$p) return ['ok' => false, 'error' => 'That project no longer exists.'];

    $bookings = project_booking_count($projectId);
    if ($bookings > 0) {
        return ['ok' => false, 'error' =>
            "This project has {$bookings} booking(s) against it. Deleting it would erase those sales records too. "
            . "Remove the bookings first if you really want it gone."];
    }

    purge_project_extras($projectId);
    db()->prepare("DELETE FROM projects WHERE id=?")->execute([$projectId]);
    log_activity('project_deleted', 'project', null, 'Project deleted – ' . $p['name'], 'building');
    return ['ok' => true];
}

/**
 * Delete one builder and everything under it.
 * @return array{ok:bool, error?:string}
 */
function delete_builder(int $builderId): array
{
    $b = row("SELECT id, name FROM builders WHERE id=?", [$builderId]);
    if (!$b) return ['ok' => false, 'error' => 'That builder no longer exists.'];

    $bookings = builder_booking_count($builderId);
    if ($bookings > 0) {
        return ['ok' => false, 'error' =>
            "This builder's projects carry {$bookings} booking(s). Deleting it would erase those sales records too. "
            . "Remove the bookings first if you really want it gone."];
    }

    foreach (rows("SELECT id FROM projects WHERE builder_id=?", [$builderId]) as $p) {
        purge_project_extras((int)$p['id']);
    }
    // Documents filed against the builder itself, not its projects
    require_once __DIR__ . '/upload.php';
    foreach (rows("SELECT file_path FROM documents WHERE entity_type='builder' AND entity_id=?", [$builderId]) as $d) {
        delete_upload($d['file_path']);
    }
    db()->prepare("DELETE FROM documents WHERE entity_type='builder' AND entity_id=?")->execute([$builderId]);

    db()->prepare("DELETE FROM builders WHERE id=?")->execute([$builderId]);
    log_activity('builder_deleted', 'builder', null, 'Builder deleted – ' . $b['name'], 'building');
    return ['ok' => true];
}

/* ============================================================
   Deleting clients
   ------------------------------------------------------------
   client_requirements, site_visits and bookings all carry a hard
   FOREIGN KEY ... ON DELETE CASCADE back to clients, so the database
   removes them on its own. Documents are the one thing it will not
   catch — they're linked by (entity_type, entity_id), not a key — so
   their rows and uploaded files are purged explicitly, same as for
   projects and builders.
   ============================================================ */

/** Bookings recorded against a client — the reason a delete may be refused. */
function client_booking_count(int $clientId): int
{
    return (int) scalar("SELECT COUNT(*) FROM bookings WHERE client_id=?", [$clientId]);
}

/** What a client delete would take with it, for the confirmation message. */
function client_delete_summary(int $clientId): array
{
    return [
        'visits'   => (int) scalar("SELECT COUNT(*) FROM site_visits WHERE client_id=?", [$clientId]),
        'docs'     => (int) scalar("SELECT COUNT(*) FROM documents WHERE entity_type='client' AND entity_id=?", [$clientId]),
        'bookings' => client_booking_count($clientId),
    ];
}

/**
 * Delete one client.
 * @return array{ok:bool, error?:string}
 */
function delete_client(int $clientId): array
{
    $c = row("SELECT id, name FROM clients WHERE id=?", [$clientId]);
    if (!$c) return ['ok' => false, 'error' => 'That client no longer exists.'];

    $bookings = client_booking_count($clientId);
    if ($bookings > 0) {
        return ['ok' => false, 'error' =>
            "This client has {$bookings} booking(s) on record. Deleting it would erase those sales records too. "
            . "Remove the bookings first if you really want it gone."];
    }

    require_once __DIR__ . '/upload.php';
    foreach (rows("SELECT file_path FROM documents WHERE entity_type='client' AND entity_id=?", [$clientId]) as $d) {
        delete_upload($d['file_path']);
    }
    db()->prepare("DELETE FROM documents WHERE entity_type='client' AND entity_id=?")->execute([$clientId]);

    db()->prepare("DELETE FROM clients WHERE id=?")->execute([$clientId]);
    log_activity('client_deleted', 'client', null, 'Client deleted – ' . $c['name'], 'leads');
    return ['ok' => true];
}
