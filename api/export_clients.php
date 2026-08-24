<?php
/**
 * RE360 — Customer requirement list as a CSV (opens in Excel).
 *
 * Honours the same filters as the Leads page, so whatever the screen is
 * showing is exactly what downloads — a filtered export that silently
 * returned everything would be worse than no export at all.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$status = trim($_GET['status'] ?? '');
$q      = trim($_GET['q'] ?? '');
$bhk    = trim($_GET['bhk'] ?? '');
$loc    = trim($_GET['loc'] ?? '');

$w = []; $params = [];
if ($status !== '') { $w[] = "c.status = ?";  $params[] = $status; }
if ($bhk    !== '') { $w[] = "r.bhk = ?";     $params[] = $bhk; }
if ($loc    !== '') { $w[] = "(r.preferred_location LIKE ? OR r.alt_location LIKE ?)"; $params[] = "%$loc%"; $params[] = "%$loc%"; }
if ($q      !== '') { $w[] = "(c.name LIKE ? OR c.mobile LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

$rows = rows("SELECT c.name, c.mobile, c.email, c.location, c.profession, c.purpose,
                     c.status, c.source, c.created_at,
                     r.bhk, r.preferred_location, r.alt_location, r.min_carpet,
                     r.agreement_budget, r.all_in_budget, r.own_contribution, r.loan_amount,
                     r.loan_required, r.preferred_floor, r.facing,
                     r.possession_within_months, r.parking, r.builder_pref, r.ready_or_uc,
                     u.name AS assigned_name,
                     (SELECT COUNT(*) FROM site_visits sv WHERE sv.client_id = c.id) AS visits,
                     (SELECT MAX(sv.visit_date) FROM site_visits sv WHERE sv.client_id = c.id) AS last_visit
              FROM clients c
              LEFT JOIN client_requirements r ON r.client_id = c.id
              LEFT JOIN users u ON u.id = c.assigned_to
              $where
              ORDER BY c.updated_at DESC", $params);

$stages  = ['new'=>'New','contacted'=>'Contacted','site_visit'=>'Site Visit',
            'negotiation'=>'Negotiation','booked'=>'Booked','lost'=>'Lost'];
$purpose = ['self'=>'Self use','investment'=>'Investment','rental'=>'Rental',
            'parents'=>'Parents','second_home'=>'Second home'];
$readyUc = ['any'=>'Any','ready'=>'Ready possession','under_construction'=>'Under construction'];

/* Plain rupees, no ₹ symbol or Cr/L wording — Excel must read these as numbers
   so the user can sort and total the budget column. */
$money = fn($v) => (int)$v > 0 ? (int)$v : '';

$filename = 'RE360-customers-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
/* PHP 8.4 deprecates fputcsv() without an explicit $escape, and the notice
   would be written straight into the download. '' is the forward-compatible
   value and also stops backslashes being treated as escapes. */
$put = fn(array $row) => fputcsv($out, $row, ',', '"', '');
fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel reads UTF-8 (₹, Marathi names) correctly

$put([
    'Name', 'Mobile', 'Email', 'City', 'Profession',
    'BHK', 'Preferred Location', 'Alternate Location', 'Min Carpet (sq.ft.)',
    'Agreement Budget', 'All-in Budget', 'Own Contribution', 'Loan Amount', 'Loan Required',
    'Possession Within (months)', 'Ready / Under Construction',
    'Preferred Floor', 'Facing', 'Parking', 'Builder Preference',
    'Purpose', 'Stage', 'Source', 'Assigned To',
    'Site Visits', 'Last Visit', 'Added On',
]);

foreach ($rows as $r) {
    $put([
        $r['name'],
        $r['mobile'],
        $r['email'],
        $r['location'],
        $r['profession'],
        $r['bhk'],
        $r['preferred_location'],
        $r['alt_location'],
        (int)$r['min_carpet'] > 0 ? (int)$r['min_carpet'] : '',
        $money($r['agreement_budget']),
        $money($r['all_in_budget']),
        $money($r['own_contribution']),
        $money($r['loan_amount']),
        $r['loan_required'] === null ? '' : ((int)$r['loan_required'] ? 'Yes' : 'No'),
        (int)$r['possession_within_months'] > 0 ? (int)$r['possession_within_months'] : '',
        $readyUc[$r['ready_or_uc']] ?? $r['ready_or_uc'],
        $r['preferred_floor'],
        $r['facing'],
        $r['parking'],
        $r['builder_pref'],
        $purpose[$r['purpose']] ?? $r['purpose'],
        $stages[$r['status']] ?? $r['status'],
        $r['source'],
        $r['assigned_name'],
        (int)$r['visits'],
        $r['last_visit'] ? date('d M Y', strtotime($r['last_visit'])) : '',
        $r['created_at'] ? date('d M Y', strtotime($r['created_at'])) : '',
    ]);
}
fclose($out);
