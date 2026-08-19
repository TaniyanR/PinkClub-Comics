<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$type = strtolower(trim((string)get('type', 'comic')));
$catalogs = [
    'comic' => [
        'label' => 'コミック',
        'floors' => ['comic'],
    ],
    'bl' => [
        'label' => 'BL',
        'floors' => ['bl'],
    ],
    'tl' => [
        'label' => 'TL',
        'floors' => ['tl'],
    ],
    'unlimited' => [
        'label' => '読み放題',
        'floors' => ['unlimited', 'unlimited_comic'],
    ],
];
if (!isset($catalogs[$type])) {
    $type = 'comic';
}
$catalog = $catalogs[$type];

$page = max(1, (int)get('page', 1));
$perPage = max(12, min(60, (int)(app_config()['pagination']['per_page'] ?? 32)));
$offset = ($page - 1) * $perPage;
$items = [];
$total = 0;

try {
    $where = [items_product_source_where('i')];
    $params = [];
    $scopeParts = [];

    foreach ($catalog['floors'] as $index => $floor) {
        $key = ':floor_' . $index;
        $scopeParts[] = 'LOWER(COALESCE(i.floor_code, "")) = ' . $key;
        $params[$key] = strtolower((string)$floor);
    }
    if ($type === 'unlimited') {
        $scopeParts[] = 'LOWER(COALESCE(i.service_code, "")) = :unlimited_service';
        $params[':unlimited_service'] = 'unlimited_book';
        $scopeParts[] = 'COALESCE(i.floor_name, "") LIKE :unlimited_name';
        $params[':unlimited_name'] = '%読み放題%';
    }

    $where[] = '(' . implode(' OR ', $scopeParts) . ')';
    $whereSql = implode(' AND ', $where);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM items i WHERE ' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT i.* FROM items i WHERE ' . $whereSql .
        ' ORDER BY i.release_date DESC, i.id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('catalog page failed: ' . $e->getMessage());
    $items = [];
    $total = 0;
}

$pg = paginate($total, $page, $perPage);
$title = $catalog['label'];
$pageDescription = $catalog['label'] . 'の新着作品を表紙・作者・価格から探せるPinkClub Comicsの作品一覧です。';
$canonicalQuery = ['type' => $type];
if ($page > 1) {
    $canonicalQuery['page'] = $page;
}
$canonicalUrl = public_url('catalog.php') . '?' . http_build_query($canonicalQuery);
if ($page > 1) {
    $relPrev = public_url('catalog.php') . '?' . http_build_query(['type' => $type, 'page' => $page - 1]);
}
if ($page < (int)($pg['pages'] ?? 1)) {
    $relNext = public_url('catalog.php') . '?' . http_build_query(['type' => $type, 'page' => $page + 1]);
}

require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => $catalog['label']],
]); ?>

<?php pcf_render_hero($catalog['label'], '表紙・作者・価格を中心に作品を探せます。'); ?>

<?php if ($items !== []): ?>
  <section class="pcf-related-grid pcf-comics-catalog-grid">
    <?php foreach ($items as $item): ?>
      <?php pcf_render_item_card(is_array($item) ? $item : [], 200, true); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('catalog.php'), ['type' => $type]); ?>
<?php else: ?>
  <?php pcf_render_empty($catalog['label'] . 'の作品データがまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
