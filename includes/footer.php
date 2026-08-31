    </main><!-- .content -->
  </div><!-- .main -->
</div><!-- .app -->

<!-- Global search overlay -->
<div class="modal-overlay" id="searchModal">
  <div class="modal" style="max-width:640px">
    <div class="search" style="max-width:none">
      <?php require_once __DIR__ . '/icons.php'; echo icon('search', 18); ?>
      <input type="text" id="searchInput" placeholder="Search builders, projects, clients, flat no..." autocomplete="off">
      <span class="kbd">Esc</span>
    </div>
    <div id="searchResults" style="margin-top:14px;max-height:50vh;overflow-y:auto"></div>
  </div>
</div>

<script src="<?= asset('assets/js/vendor/chart.umd.min.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if (!empty($pageScripts)) foreach ((array)$pageScripts as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
