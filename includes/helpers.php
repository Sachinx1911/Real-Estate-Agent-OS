<?php
/**
 * RE360 — shared helper functions
 */

/* ---------------- Output / escaping ---------------- */
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Null-safe integer formatting — DB columns like carpet/units can be NULL,
 * and PHP 8.1+ deprecates passing null straight to number_format(). */
function num($v, string $ifEmpty = '0'): string
{
    if ($v === null || $v === '') return $ifEmpty;
    return number_format((float)$v);
}

/* ---------------- Currency (Indian) ----------------
 * 9250000  -> "₹92.5 L"
 * 12000000 -> "₹1.2 Cr"
 * 1248 (already crore total) use money_cr()
 */
function money(?int $rupees): string
{
    $r = (int)$rupees;
    if ($r <= 0) return '₹0';
    if ($r >= 10000000) {          // >= 1 crore
        $cr = $r / 10000000;
        return '₹' . rtrim(rtrim(number_format($cr, 2), '0'), '.') . ' Cr';
    }
    if ($r >= 100000) {            // >= 1 lakh
        $l = $r / 100000;
        return '₹' . rtrim(rtrim(number_format($l, 2), '0'), '.') . ' L';
    }
    return '₹' . number_format($r);
}

/* Full grouped rupees: 9250000 -> "₹92,50,000" (Indian grouping) */
function money_full(?int $rupees): string
{
    $r = (int)$rupees;
    $s = (string)abs($r);
    if (strlen($s) <= 3) return '₹' . $s;
    $last3 = substr($s, -3);
    $rest  = substr($s, 0, -3);
    $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return '₹' . $rest . ',' . $last3;
}

/* Large totals as crores: money_cr(12480000000) -> "₹1,248 Cr" */
function money_cr(?int $rupees): string
{
    $cr = (int)round(((int)$rupees) / 10000000);
    return '₹' . number_format($cr) . ' Cr';
}

/* Effective price per sq.ft. */
function price_per_sqft(?int $price, ?int $carpet): string
{
    $c = (int)$carpet;
    if ($c <= 0) return '—';
    return '₹' . number_format((int)round(((int)$price) / $c)) . '/sq.ft.';
}

/* ---------------- Inventory freshness ----------------
 * Returns ['level'=>'fresh|good|warn|stale','color'=>hex,'label'=>'..','days'=>n]
 * 🟢 <3d  🟡 3-7d  🟠 7-15d  🔴 >15d / never verified
 */
function freshness(?string $verifiedAt): array
{
    if (empty($verifiedAt)) {
        return ['level' => 'stale', 'color' => '#ef4444', 'label' => 'Not verified', 'days' => null];
    }
    $days = (int) floor((time() - strtotime($verifiedAt)) / 86400);
    if ($days < 3)  return ['level' => 'fresh', 'color' => '#22c55e', 'label' => 'Updated ' . $days . 'd', 'days' => $days];
    if ($days <= 7) return ['level' => 'good',  'color' => '#eab308', 'label' => $days . 'd ago', 'days' => $days];
    if ($days <= 15)return ['level' => 'warn',  'color' => '#f59e0b', 'label' => $days . 'd ago', 'days' => $days];
    return ['level' => 'stale', 'color' => '#ef4444', 'label' => $days . 'd ago', 'days' => $days];
}

/* Inventory status badge color */
function status_color(string $status): string
{
    $map = [
        'available'  => 'green',
        'hold'       => 'amber',
        'token'      => 'violet',
        'booked'     => 'blue',
        'agreement'  => 'teal',
        'registered' => 'teal',
        'sold'       => 'grey',
        'cancelled'  => 'grey',
        'blocked'    => 'grey',
    ];
    return $map[$status] ?? 'grey';
}

/* Relative time: "2h ago", "Today 11:30 AM" */
function time_ago(?string $dt): string
{
    if (empty($dt)) return '';
    $ts = strtotime($dt);
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400 && date('Y-m-d') === date('Y-m-d', $ts)) return 'Today ' . date('g:i A', $ts);
    return date('d M Y, g:i A', $ts);
}

/* Nice date */
function fdate(?string $dt, string $fmt = 'd M Y'): string
{
    if (empty($dt) || $dt === '0000-00-00') return '—';
    return date($fmt, strtotime($dt));
}

/* ---------------- Client ↔ Project match score ----------------
 * Scores a project against a client requirement.
 *
 * The important part: budget and carpet are judged against the flats that
 * ACTUALLY match the client's BHK and are still available — not against the
 * project's cheapest unit. Otherwise a project with one cheap 1 BHK would
 * score 100% for a 2 BHK buyer, which is useless on a sales call.
 *
 * $req  = ['bhk','all_in_budget','min_carpet','possession_within_months','locations'=>[]]
 * $proj = project row + agg fields: configs, max_carpet, and (ideally)
 *         fit_count / fit_price_min / fit_carpet_max from matching inventory.
 * Returns ['score'=>0-100, 'reasons'=>[['label','ok'], ...], 'fit_count'=>int]
 */
function match_score(array $req, array $proj): array
{
    $reasons = [];
    $score = 0; $max = 0;

    $bhk      = strtolower(trim($req['bhk'] ?? ''));
    $budget   = (int)($req['all_in_budget'] ?? 0);
    $needCarp = (int)($req['min_carpet'] ?? 0);

    // Flats that match the requested configuration and are still available
    $fitCount = isset($proj['fit_count']) ? (int)$proj['fit_count'] : null;
    $fitPrice = isset($proj['fit_price_min']) ? (int)$proj['fit_price_min'] : 0;
    $fitCarp  = isset($proj['fit_carpet_max']) ? (int)$proj['fit_carpet_max'] : 0;

    // Fall back to project-level figures when inventory has not been captured yet
    if (!$fitPrice) $fitPrice = (int)($proj['price_min'] ?? 0);
    if (!$fitCarp)  $fitCarp  = (int)($proj['max_carpet'] ?? 0);

    /* ---- Location (30) ---- */
    $max += 30;
    $locs = array_filter(array_map('trim', (array)($req['locations'] ?? [])));
    $node = strtolower(trim($proj['node'] ?? ''));
    $locOk = empty($locs);
    foreach ($locs as $l) {
        if ($l !== '' && (strpos($node, strtolower($l)) !== false || strpos(strtolower($l), $node) !== false)) { $locOk = true; break; }
    }
    if ($locOk) $score += 30;
    $reasons[] = ['label' => 'Location', 'ok' => $locOk];

    /* ---- Configuration (20) — must have the BHK, and preferably in stock ---- */
    $max += 20;
    $configs = strtolower($proj['configs'] ?? '');
    $hasConfig = $bhk === '' || strpos($configs, $bhk) !== false;
    $inStock   = $fitCount === null ? $hasConfig : ($fitCount > 0);
    if ($hasConfig && $inStock)      $score += 20;   // offered and available
    elseif ($hasConfig)              $score += 8;    // offered but nothing free right now
    $reasons[] = ['label' => 'Configuration', 'ok' => ($hasConfig && $inStock)];

    /* ---- Budget (30) — against the cheapest MATCHING flat ---- */
    $max += 30;
    $budgetOk = false;
    if ($budget <= 0 || $fitPrice <= 0) {
        $budgetOk = true; $score += 30;            // no budget stated → not a blocker
    } elseif ($fitPrice <= $budget) {
        $budgetOk = true; $score += 30;
    } elseif ($fitPrice <= $budget * 1.10) {
        $score += 18;                              // slight stretch — worth showing
    } elseif ($fitPrice <= $budget * 1.25) {
        $score += 8;                               // real stretch
    }
    $reasons[] = ['label' => 'Budget', 'ok' => $budgetOk];

    /* ---- Carpet (10) ---- */
    $max += 10;
    $carpetOk = $needCarp <= 0 || $fitCarp >= $needCarp;
    if ($carpetOk)                        $score += 10;
    elseif ($fitCarp >= $needCarp * 0.92) $score += 5;   // marginally smaller
    $reasons[] = ['label' => 'Carpet', 'ok' => $carpetOk];

    /* ---- Possession (10) ---- */
    $max += 10;
    $need = (int)($req['possession_within_months'] ?? 0);
    $possOk = true;
    if ($need > 0 && !empty($proj['proposed_completion']) && $proj['proposed_completion'] !== '0000-00-00') {
        $months = (strtotime($proj['proposed_completion']) - time()) / (30 * 86400);
        $possOk = $months <= $need;
        if ($possOk)                  $score += 10;
        elseif ($months <= $need + 6) $score += 5;       // a bit late
    } else {
        $score += 10;
    }
    $reasons[] = ['label' => 'Possession', 'ok' => $possOk];

    return [
        'score'     => (int)round($score / max($max, 1) * 100),
        'reasons'   => $reasons,
        'fit_count' => $fitCount ?? 0,
    ];
}

/**
 * SQL fragment that adds per-project "matching flat" aggregates used by
 * match_score(). Pass the client's BHK; bind it three times.
 */
function match_fit_sql(): string
{
    return "(SELECT COUNT(*) FROM inventory i2 WHERE i2.project_id=p.id AND i2.status='available' AND i2.config = ?) AS fit_count,
            (SELECT MIN(i3.price) FROM inventory i3 WHERE i3.project_id=p.id AND i3.status='available' AND i3.config = ?) AS fit_price_min,
            (SELECT MAX(i4.carpet) FROM inventory i4 WHERE i4.project_id=p.id AND i4.status='available' AND i4.config = ?) AS fit_carpet_max";
}

/* ---------------- Activity log ---------------- */
function log_activity(string $action, string $entityType, ?int $entityId, string $message, string $icon = 'info'): void
{
    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $st = db()->prepare(
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, message, icon)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([$uid, $action, $entityType, $entityId, $message, $icon]);
    } catch (Throwable $ex) { /* non-fatal */ }
}

/* ---------------- Small query helpers ---------------- */
function scalar(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}
function rows(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
function row(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

/* Build a URL to a page */
function url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

/* Percent growth pill */
function growth_pill($value, string $suffix = ''): string
{
    if ($value === null || $value === '') return '';
    $up = (float)$value >= 0;
    $arrow = $up ? '↑' : '↓';
    $cls = $up ? 'up' : 'down';
    $txt = (is_numeric($value) ? ($up ? '+' : '') . $value : $value) . $suffix;
    return "<span class=\"pill $cls\">$arrow " . e($txt) . "</span>";
}
