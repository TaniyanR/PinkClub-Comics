<?php

declare(strict_types=1);

require_once __DIR__ . '/public/_bootstrap.php';
require_once __DIR__ . '/lib/repository.php';
require_once __DIR__ . '/public/partials/public_ui.php';

function comics_home_items(string $type, int $limit = 8): array
{
    $limit = max(1, min(20, $limit));
    $where = [items_product_source_where('i')];
    $params = [];

    if ($type === 'unlimited') {
        $where[] = '(
            LOWER(COALESCE(i.floor_code, "")) IN ("unlimited", "unlimited_comic")
            OR LOWER(COALESCE(i.service_code, "")) = "unlimited_book"
            OR COALESCE(i.floor_name, "") LIKE :floor_name
        )';
        $params[':floor_name'] = '%読み放題%';
    } else {
        $where[] = 'LOWER(COALESCE(i.floor_code, "")) = :floor_code';
        $params[':floor_code'] = strtolower($type);
    }

    try {
        $stmt = db()->prepare(
            'SELECT i.* FROM items i
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.release_date DESC, i.id DESC
             LIMIT ' . $limit
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('comics home items failed: ' . $type . ': ' . $e->getMessage());
        return [];
    }
}

$sections = [
    'comic' => ['label' => '新着コミック', 'items' => comics_home_items('comic')],
    'bl' => ['label' => '新着BL', 'items' => comics_home_items('bl')],
    'tl' => ['label' => '新着TL', 'items' => comics_home_items('tl')],
    'unlimited' => ['label' => '読み放題', 'items' => comics_home_items('unlimited')],
];

$title = 'トップ';
$pageDescription = 'コミック・BL・TL・読み放題を表紙、作者、ジャンル、シリーズから探せるPinkClub Comics。';
$canonicalUrl = rtrim(BASE_URL, '/') . '/';
require __DIR__ . '/public/partials/header.php';
?>
<section class="pcf-hero">
  <h1 class="pcf-hero__title">PinkClub Comics</h1>
  <p class="pcf-hero__subtitle">コミック・BL・TL・読み放題を、作品や作者から探せます。</p>
</section>

<nav class="pcf-chip-list" aria-label="作品を探す" style="margin-bottom:24px;">
  <a class="pcf-chip" href="<?= e(public_url('authors.php')) ?>">作者から探す</a>
  <a class="pcf-chip" href="<?= e(public_url('genres.php')) ?>">ジャンルから探す</a>
  <a class="pcf-chip" href="<?= e(public_url('series_list.php')) ?>">シリーズから探す</a>
  <a class="pcf-chip" href="<?= e(public_url('labels.php')) ?>">レーベルから探す</a>
</nav>

<?php foreach ($sections as $type => $section): ?>
  <section class="pcf-comics-home-section" style="margin-bottom:30px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <h2 class="pcf-section-title"><?= e((string)$section['label']) ?></h2>
      <a href="<?= e(public_url('catalog.php?type=' . $type)) ?>">もっと見る</a>
    </div>
    <?php if ($section['items'] !== []): ?>
      <div class="pcf-related-grid pcf-comics-catalog-grid">
        <?php foreach ($section['items'] as $item): ?>
          <?php pcf_render_item_card(is_array($item) ? $item : [], 180, true); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?php pcf_render_empty((string)$section['label'] . 'の作品データがまだありません。'); ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php require __DIR__ . '/public/partials/footer.php'; ?>
