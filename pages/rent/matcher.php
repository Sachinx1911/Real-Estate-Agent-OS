<?php
/**
 * Rent → Matcher. Score available flats against one enquiry.
 *
 * The sale-side match_score() is not reused: renting turns on a monthly
 * budget, furnishing and a move-in date, none of which that function knows.
 */

if ($preRender) {
    $seekerId = (int)($_GET['seeker'] ?? 0);
    $seeker   = $seekerId ? row("SELECT * FROM rent_seekers WHERE id=?", [$seekerId]) : null;
    $allSeekers = rows("SELECT id, name, config, preferred_location FROM rent_seekers
                        WHERE status IN ('searching','shown') ORDER BY updated_at DESC");

    $matches = [];
    if ($seeker) {
        // Only flats that can actually be offered
        $cands = rows("SELECT f.*, o.name AS o_name, o.mobile AS o_mobile
                       FROM rent_flats f LEFT JOIN rent_owners o ON o.id=f.owner_id
                       WHERE f.status='available'");

        $bmin = (int)$seeker['budget_min'];
        $bmax = (int)$seeker['budget_max'];

        foreach ($cands as $f) {
            $score = 0; $max = 0; $why = [];

            // Location (35) — the one thing people rarely bend on
            $max += 35;
            $wantLoc = strtolower(trim($seeker['preferred_location'] ?? ''));
            $hasLoc  = strtolower(trim($f['location'] ?? ''));
            $locOk = $wantLoc === '' || ($hasLoc !== '' && $hasLoc === $wantLoc);
            if ($locOk) $score += 35;
            $why[] = ['Location', $locOk];

            // Rent against their monthly range (30)
            $max += 30;
            $rent = (int)$f['rent'];
            $budgetOk = false;
            if (!$bmax || !$rent) { $budgetOk = true; $score += 30; }
            elseif ($rent <= $bmax) { $budgetOk = true; $score += 30; }
            elseif ($rent <= $bmax * 1.10) { $score += 18; }
            elseif ($rent <= $bmax * 1.20) { $score += 8; }
            $why[] = ['Rent', $budgetOk];

            // Configuration (20)
            $max += 20;
            $cfgOk = empty($seeker['config']) || $seeker['config'] === $f['config'];
            if ($cfgOk) $score += 20;
            $why[] = ['BHK', $cfgOk];

            // Furnishing (10) — "any" never counts against a flat
            $max += 10;
            $wantFurn = $seeker['furnishing'] ?? 'any';
            $furnOk = $wantFurn === 'any' || $wantFurn === '' || $wantFurn === $f['furnishing'];
            if ($furnOk) $score += 10;
            $why[] = ['Furnishing', $furnOk];

            // Free by the time they need it (5)
            $max += 5;
            $dateOk = true;
            if (!empty($seeker['needed_from']) && !empty($f['available_from'])) {
                $dateOk = strtotime($f['available_from']) <= strtotime($seeker['needed_from']);
            }
            if ($dateOk) $score += 5;
            $why[] = ['Ready in time', $dateOk];

            $matches[] = [
                'f' => $f,
                'score' => (int) round($score / max($max,1) * 100),
                'why' => $why,
            ];
        }
        usort($matches, fn($a,$b) => $b['score'] - $a['score']);
    }
    return;
}
?>
<form method="get" class="card" style="margin-bottom:18px;padding:14px">
  <input type="hidden" name="page" value="rent"><input type="hidden" name="tab" value="matcher">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <span class="muted small">Match flats for</span>
    <select class="select" name="seeker" onchange="this.form.submit()" style="min-width:260px">
      <option value="">— pick an enquiry —</option>
      <?php foreach ($allSeekers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $seekerId===(int)$s['id']?'selected':'' ?>>
          <?= e($s['name']) ?><?= $s['config'] ? ' · '.e($s['config']) : '' ?><?= $s['preferred_location'] ? ' · '.e($s['preferred_location']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <a class="btn ghost sm" href="<?= url('rent',['tab'=>'enquiries']) ?>">All enquiries</a>
  </div>
</form>

<?php if (!$seeker): ?>
  <div class="card empty"><?= icon('search',40) ?>
    <div style="margin-top:10px">Pick an enquiry above to see which available flats fit.</div>
  </div>
<?php else:
  $bmin=(int)$seeker['budget_min']; $bmax=(int)$seeker['budget_max'];
  $budgetTxt = $bmin && $bmax ? money_full($bmin).' – '.money_full($bmax) : ($bmax ? 'up to '.money_full($bmax) : '—');
?>
  <div class="card" style="margin-bottom:18px">
    <div class="card-head"><h3><?= e($seeker['name']) ?></h3>
      <a class="link" href="<?= url('rent_seeker_form',['id'=>$seeker['id']]) ?>">Edit requirement</a></div>
    <div class="chip-row">
      <span class="chip"><?= icon('location',14) ?> <?= e($seeker['preferred_location'] ?: 'Any location') ?></span>
      <span class="chip"><?= e($seeker['config'] ?: 'Any BHK') ?></span>
      <span class="chip"><?= $budgetTxt ?> / month</span>
      <span class="chip"><?= e($furnLabel[$seeker['furnishing']] ?? 'Any') ?></span>
      <span class="chip"><?= e(ucfirst($seeker['family_type'])) ?></span>
      <?php if ($seeker['needed_from']): ?><span class="chip">From <?= e(fdate($seeker['needed_from'],'d M Y')) ?></span><?php endif; ?>
    </div>
  </div>

  <?php if (!$matches): ?>
    <div class="card empty">No flats are available right now to match against.</div>
  <?php else: ?>
    <div class="grid" style="grid-template-columns:repeat(3,1fr)">
      <?php foreach ($matches as $m): $f=$m['f'];
        $cls = $m['score']>=85 ? '' : ($m['score']>=65 ? 'mid' : 'low'); ?>
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
            <div>
              <div style="font-weight:700;font-size:14px"><?= e($f['building_name']) ?></div>
              <div class="muted tiny"><?= e(trim(($f['flat_no'] ?: '').' · '.($f['location'] ?: ''), ' ·')) ?></div>
            </div>
            <span class="match-badge <?= $cls ?>"><?= $m['score'] ?>%</span>
          </div>

          <div style="display:flex;justify-content:space-between;margin:12px 0;padding:10px 0;
                      border-top:1px solid var(--border-soft);border-bottom:1px solid var(--border-soft)">
            <div><div class="muted tiny">Rent</div><div style="font-weight:700;font-size:13px"><?= (int)$f['rent'] ? money_full($f['rent']) : '—' ?></div></div>
            <div><div class="muted tiny">Deposit</div><div style="font-weight:700;font-size:13px"><?= (int)$f['deposit'] ? money_full($f['deposit']) : '—' ?></div></div>
            <div style="text-align:right"><div class="muted tiny">BHK</div><div style="font-weight:700;font-size:13px"><?= e($f['config'] ?: '—') ?></div></div>
          </div>

          <?php foreach ($m['why'] as $w): ?>
            <div class="tiny" style="color:<?= $w[1] ? 'var(--green)' : 'var(--amber)' ?>">
              <?= $w[1] ? '&#10003;' : '&#9888;' ?> <?= e($w[0]) ?>
            </div>
          <?php endforeach; ?>

          <div class="muted tiny" style="margin-top:10px">
            <?= e($furnLabel[$f['furnishing']] ?? '') ?> ·
            <?= $f['available_from'] ? 'from '.e(fdate($f['available_from'],'d M Y')) : 'available now' ?>
          </div>
          <div class="muted tiny" style="margin-top:4px">
            Owner: <?= e($f['o_name'] ?: $f['owner_name']) ?>
            <?php $mob = $f['o_mobile'] ?: $f['owner_mobile']; if ($mob): ?>
              · <a class="link" href="tel:<?= e($mob) ?>"><?= e($mob) ?></a><?php endif; ?>
          </div>

          <a class="btn ghost sm" style="margin-top:12px"
             href="<?= url('rent',['tab'=>'visits','flat'=>$f['id'],'seeker'=>$seeker['id']]) ?>">
            <?= icon('visits',14) ?> Arrange a visit
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
