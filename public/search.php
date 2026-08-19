<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$query = trim(safe_str($_GET['q'] ?? '', 200));
$page = normalize_int((int)($_GET['page'] ?? 1), 1, 100000);
$limit = max(12, min(60, (int)(app_config()['pagination']['per_page'] ?? 32)));
$offset = ($page - 1) * $limit;
$items = [];
$total = 0;

if ($query !== '') {
    try {
        $like = '%' . $query . '%';
        $where = items_product_source_where('i') . '
            AND (
                i.title LIKE :title_query
                OR i.raw_json LIKE :raw_query
                OR i.content_id = :content_id
                OR i.product_id = :product_id
            )';

        $countStmt = db()->prepare('SELECT COUNT(*) FROM items i WHERE ' . $where);
        $countStmt->bindValue(':title_query', $like, PDO::PARAM_STR);
        $countStmt->bindValue(':raw_query', $like, PDO::PARAM_STR);
        $countStmt->bindValue(':content_id', $query, PDO::PARAM_STR);
        $countStmt->bindValue(':product_id', $query, PDO::PARAM_STR);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = db()->prepare(
            'SELECT i.* FROM items i
             WHERE ' . $where . '
             ORDER BY i.release_date DESC, i.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':title_query', $like, PDO::PARAM_STR);
        $stmt->bindValue(':raw_query', $like, PDO::PARAM_STR);
        $stmt->bindValue(':content_id', $query, PDO::PARAM_STR);
        $stmt->bindValue(':product_id', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('comics search failed: ' . $e->getMessage());
        $items = [];
        $total = 0;
    }
}

$pg = paginate($total, $page, $limit);
$title = '作品検索';
$pageDescription = $query !== ''
    ? mb_strimwidth('「' . $query . '」の作品・作者検索結果です。', 0, 150, '…', 'UTF-8')
    : '作品名、作者名、ジャンル、シリーズから漫画を検索できます。';
$robotsMeta = 'noindex,follow';
$canonicalQuery = [];
if ($query !== '') {
    $canonicalQuery['q'] = $query;
}
if ($page > 1) {
    $canonicalQuery['page'] = $page;
}
$canonicalUrl = public_url('search.php') . ($canonicalQuery !== [] ? '?' . http_build_query($canonicalQuery) : '');
if ($page > 1) {
    $relPrev = public_url('search.php') . '?' . http_build_query(['q' => $query, 'page' => $page - 1]);
}
if ($page < (int)($pg['pages'] ?? 1)) {
    $relNext = public_url('search.php') . '?' . http_build_query(['q' => $query, 'page' => $page + 1]);
}

require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero(
    '作品検索',
    $query !== '' ? '「' . $query . '」の検索結果です。' : '作品名、作者名、ジャンル、シリーズから検索できます。'
); ?>

<?php if ($query === ''): ?>
  <?php pcf_render_empty('検索キーワードを入力してください。'); ?>
<?php elseif ($items !== []): ?>
  <p class="pcf-list-card__meta">検索結果：<?= e(number_format($total)) ?>件</p>
  <section class="pcf-related-grid pcf-comics-catalog-grid">
    <?php foreach ($items as $item): ?>
      <?php pcf_render_item_card(is_array($item) ? $item : [], 200, true); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('search.php'), ['q' => $query]); ?>
<?php else: ?>
  <?php pcf_render_empty('検索条件に一致する作品がありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
