<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = [];
try {
    $stmt = db()->query(
        'SELECT a.id, a.dmm_id, a.name, a.ruby
         FROM authors a
         WHERE TRIM(COALESCE(a.name, "")) <> ""
           AND EXISTS (
             SELECT 1
             FROM item_authors ia
             INNER JOIN items i ON i.id = ia.item_id
             WHERE (ia.dmm_id = a.dmm_id OR ia.author_name = a.name)
               AND ' . items_product_source_where('i') . '
           )
         ORDER BY a.name ASC, a.id ASC
         LIMIT 5000'
    );
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
    error_log('authors directory failed: ' . $e->getMessage());
    $rows = [];
}

$displayRows = [];
$seen = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $name = trim((string)($row['name'] ?? ''));
    if ($name === '' || pcf_is_noise_name($name)) {
        continue;
    }
    $key = mb_strtolower($name, 'UTF-8');
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $displayRows[] = $row;
}

$kanaOrder = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
$kanaGroups = array_fill_keys($kanaOrder, []);
$alphaGroups = [];
$otherRows = [];

$resolveIndex = static function (string $name): array {
    $ch = mb_substr(trim($name), 0, 1);
    if ($ch === '') {
        return ['type' => 'other', 'key' => ''];
    }
    $h = mb_convert_kana($ch, 'c', 'UTF-8');
    if (preg_match('/^[ぁ-お]/u', $h)) { return ['type' => 'kana', 'key' => 'あ']; }
    if (preg_match('/^[か-ご]/u', $h)) { return ['type' => 'kana', 'key' => 'か']; }
    if (preg_match('/^[さ-ぞ]/u', $h)) { return ['type' => 'kana', 'key' => 'さ']; }
    if (preg_match('/^[た-ど]/u', $h)) { return ['type' => 'kana', 'key' => 'た']; }
    if (preg_match('/^[な-の]/u', $h)) { return ['type' => 'kana', 'key' => 'な']; }
    if (preg_match('/^[は-ぽ]/u', $h)) { return ['type' => 'kana', 'key' => 'は']; }
    if (preg_match('/^[ま-も]/u', $h)) { return ['type' => 'kana', 'key' => 'ま']; }
    if (preg_match('/^[や-よ]/u', $h)) { return ['type' => 'kana', 'key' => 'や']; }
    if (preg_match('/^[ら-ろ]/u', $h)) { return ['type' => 'kana', 'key' => 'ら']; }
    if (preg_match('/^[わ-ん]/u', $h)) { return ['type' => 'kana', 'key' => 'わ']; }
    if (preg_match('/^[A-Za-z]/', $ch)) { return ['type' => 'alpha', 'key' => strtoupper($ch)]; }
    return ['type' => 'other', 'key' => ''];
};

foreach ($displayRows as $row) {
    $index = $resolveIndex((string)($row['name'] ?? ''));
    if ($index['type'] === 'kana') {
        $kanaGroups[$index['key']][] = $row;
    } elseif ($index['type'] === 'alpha') {
        $alphaGroups[$index['key']][] = $row;
    } else {
        $otherRows[] = $row;
    }
}
ksort($alphaGroups);

$title = '作者一覧';
$pageDescription = 'PinkClub Comicsの作者一覧。作者名からコミック・BL・TL・読み放題作品を探せます。';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('作者一覧', '作者名から作品を探せます。'); ?>

<?php if ($displayRows !== []): ?>
  <div class="pcf-kana-directory">
    <?php foreach ($kanaGroups as $kana => $groupRows): ?>
      <?php if ($groupRows === []): continue; endif; ?>
      <section class="pcf-index-block" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title"><?= e($kana) ?>行</h2>
        <div class="pcf-list-card__meta pcf-chip-list">
          <?php foreach ($groupRows as $row): ?>
            <a class="pcf-chip" href="<?= e(public_url('author.php?id=' . (int)($row['id'] ?? 0))) ?>"><?= e((string)($row['name'] ?? '')) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if ($alphaGroups !== []): ?>
      <section class="pcf-index-block" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title">A~Z</h2>
        <?php foreach ($alphaGroups as $letter => $groupRows): ?>
          <div class="pcf-list-card__meta pcf-chip-list">
            <strong><?= e($letter) ?></strong>
            <?php foreach ($groupRows as $row): ?>
              <a class="pcf-chip" href="<?= e(public_url('author.php?id=' . (int)($row['id'] ?? 0))) ?>"><?= e((string)($row['name'] ?? '')) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($otherRows !== []): ?>
      <section class="pcf-index-block" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title">その他</h2>
        <div class="pcf-list-card__meta pcf-chip-list">
          <?php foreach ($otherRows as $row): ?>
            <a class="pcf-chip" href="<?= e(public_url('author.php?id=' . (int)($row['id'] ?? 0))) ?>"><?= e((string)($row['name'] ?? '')) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php pcf_render_empty('作者データがありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
