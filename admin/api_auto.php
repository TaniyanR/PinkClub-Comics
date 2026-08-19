<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/scheduler.php';
require_once __DIR__ . '/../lib/repository.php';

auth_require_admin();

$title = '自動設定';
$message = '';
$messageType = 'success';

$intervalOptions = [10, 20, 30, 60, 120, 180, 360, 720];
$batchOptions = [1, 10, 20, 30, 50, 100, 200, 300, 500];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));

    $enabled = post('item_sync_enabled', '0') === '1' ? '1' : '0';
    $interval = (int)post('item_sync_interval_minutes', 60);
    if (!in_array($interval, $intervalOptions, true)) {
        $interval = 60;
    }

    $batch = (int)post('item_sync_batch', 100);
    if (!in_array($batch, $batchOptions, true)) {
        $batch = 100;
    }

    $excludeKeywords = [];
    for ($i = 1; $i <= 5; $i++) {
        $value = trim((string)post('item_sync_exclude_' . $i, ''));
        if ($value !== '') {
            $excludeKeywords[] = $value;
        }
    }

    site_setting_set_many([
        'item_sync_enabled' => $enabled,
        'item_sync_interval_minutes' => (string)$interval,
        'item_sync_batch' => (string)$batch,
        'item_sync_exclude_keywords' => implode("\n", $excludeKeywords),
    ]);

    $pdo = db();
    scheduler_ensure_schedule_table($pdo);
    scheduler_seed_default_schedules($pdo);
    scheduler_apply_auto_settings($pdo);
    $message = '自動設定を保存しました。';
}

$settings = settings_get();
$currentInterval = (int)($settings['item_sync_interval_minutes'] ?? 60);
if (!in_array($currentInterval, $intervalOptions, true)) {
    $currentInterval = 60;
}
$currentBatch = (int)($settings['item_sync_batch'] ?? 100);
if (!in_array($currentBatch, $batchOptions, true)) {
    $currentBatch = 100;
}
$enabled = settings_bool('item_sync_enabled', false);
$excludeLines = preg_split('/\R/u', site_setting_get('item_sync_exclude_keywords', '')) ?: [];

$pdo = db();
scheduler_ensure_schedule_table($pdo);
scheduler_seed_default_schedules($pdo);
scheduler_apply_auto_settings($pdo);
$stateStmt = $pdo->query("SELECT job_key, last_run_at, last_success, last_message, next_offset, lock_until FROM sync_job_state WHERE job_key = 'items' LIMIT 1");
$autoStates = $stateStmt ? $stateStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$storedItemCount = null;
$publicItemCount = null;
$nonPublicItemCount = null;
try {
    if (db_table_exists('items')) {
        $storedStmt = $pdo->query('SELECT COUNT(*) FROM items');
        $storedItemCount = $storedStmt ? (int)$storedStmt->fetchColumn() : null;

        $publicWhere = items_product_source_where('items');
        $publicStmt = $pdo->query('SELECT COUNT(*) FROM items WHERE ' . $publicWhere);
        $publicItemCount = $publicStmt ? (int)$publicStmt->fetchColumn() : null;

        if ($storedItemCount !== null && $publicItemCount !== null) {
            $nonPublicItemCount = max(0, $storedItemCount - $publicItemCount);
        }
    }
} catch (Throwable $e) {
    error_log('admin comics item counts failed: ' . $e->getMessage());
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>自動設定</h1>
  <?php if ($message !== ''): ?>
    <div class="admin-notice <?= $messageType === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>">
      <p><?= e($message) ?></p>
    </div>
  <?php endif; ?>

  <form method="post" class="stack" style="max-width:980px;">
    <?= csrf_input() ?>

    <div style="display:grid;grid-template-columns:180px minmax(280px,1fr);gap:12px 16px;align-items:center;">
      <div><strong>自動更新を有効化</strong></div>
      <div style="text-align:left;">
        <input type="hidden" name="item_sync_enabled" value="0">
        <label style="display:inline-flex;align-items:center;gap:10px;"><input type="checkbox" name="item_sync_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> <span>ON</span></label>
      </div>

      <div><strong>自動更新間隔</strong></div>
      <div>
        <select name="item_sync_interval_minutes" style="width:100%;">
          <?php foreach ($intervalOptions as $value): ?>
            <option value="<?= e((string)$value) ?>" <?= $currentInterval === $value ? 'selected' : '' ?>><?= e((string)$value) ?>分</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div><strong>1回の取得作品数</strong></div>
      <div>
        <select name="item_sync_batch" style="width:100%;">
          <?php foreach ($batchOptions as $value): ?>
            <option value="<?= e((string)$value) ?>" <?= $currentBatch === $value ? 'selected' : '' ?>><?= e((string)$value) ?>件</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h2 style="margin-top:20px;">除外キーワード（最大5）</h2>
    <p class="admin-form-note">作品タイトルに含まれる語句で、取得対象から除外できます。</p>
    <div style="display:grid;grid-template-columns:180px minmax(280px,1fr);gap:12px 16px;align-items:center;">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <div><strong>除外キーワード<?= e((string)$i) ?></strong></div>
        <div><input type="text" name="item_sync_exclude_<?= e((string)$i) ?>" value="<?= e((string)($excludeLines[$i - 1] ?? '')) ?>" style="width:100%;"></div>
      <?php endfor; ?>
    </div>

    <div class="admin-actions" style="margin-top:20px;"><button type="submit">保存</button></div>
  </form>

  <h2 style="margin-top:24px;">作品数の内訳</h2>
  <?php if ($storedItemCount !== null && $publicItemCount !== null && $nonPublicItemCount !== null): ?>
    <div class="admin-status-grid">
      <article class="admin-card admin-status-card"><strong>保存済み作品</strong><p><?= e(number_format($storedItemCount)) ?>件</p></article>
      <article class="admin-card admin-status-card"><strong>公開作品</strong><p><?= e(number_format($publicItemCount)) ?>件</p></article>
      <article class="admin-card admin-status-card"><strong>公開前・除外</strong><p><?= e(number_format($nonPublicItemCount)) ?>件</p></article>
    </div>
    <p class="admin-form-note">公開前・除外には、配信開始日前の作品や公開対象外のデータなどが含まれます。</p>
  <?php else: ?>
    <p class="admin-form-note">作品数の内訳を取得できませんでした。</p>
  <?php endif; ?>

  <h2 style="margin-top:24px;">自動更新状態</h2>
  <table class="admin-table">
    <tr><th>ジョブ</th><th>最終実行日時</th><th>状態</th><th>メッセージ</th><th>次回offset</th><th>ロック期限</th></tr>
    <?php if ($autoStates === []): ?>
      <tr><td colspan="6">まだ実行履歴がありません。</td></tr>
    <?php else: ?>
      <?php foreach ($autoStates as $state): ?>
        <tr>
          <td>作品取得</td>
          <td><?= e((string)($state['last_run_at'] ?? '')) ?></td>
          <td><?= ((int)($state['last_success'] ?? 0) === 1) ? '成功' : '未成功' ?></td>
          <td><?= e((string)($state['last_message'] ?? '')) ?></td>
          <td><?= e((string)($state['next_offset'] ?? '1')) ?></td>
          <td><?= e((string)($state['lock_until'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </table>
  <p class="admin-form-note">コミック・BL・TL・読み放題は、設定済みの取得先をcron実行ごとに順番に巡回します。作者・ジャンル・シリーズ等は作品データから保存されます。</p>

  <?php if ($enabled): ?>
    <div class="admin-notice admin-notice--success" id="auto-timer-status">
      <p>自動更新はONです。同期はcronでのみ実行され、この画面を開いているだけではAPI取得しません。</p>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
