<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$author = false;
$items = [];

try {
    $stmt = db()->prepare('SELECT id, dmm_id, name, ruby FROM authors WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('author fetch failed: ' . $e->getMessage());
}

if (!$author) {
    require __DIR__ . '/404.php';
}

try {
    $authorDmmId = trim((string)($author['dmm_id'] ?? ''));
    $stmt = db()->prepare(
        'SELECT DISTINCT i.*
         FROM items i
         INNER JOIN item_authors ia ON ia.item_id = i.id
         WHERE ' . items_product_source_where('i') . '
           AND (
             (:dmm_present <> "" AND ia.dmm_id = :dmm_match)
             OR ia.author_name = :author_name
           )
         ORDER BY i.release_date DESC, i.id DESC
         LIMIT 120'
    );
    $stmt->bindValue(':dmm_present', $authorDmmId, PDO::PARAM_STR);
    $stmt->bindValue(':dmm_match', $authorDmmId, PDO::PARAM_STR);
    $stmt->bindValue(':author_name', (string)($author['name'] ?? ''), PDO::PARAM_STR);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('author items failed: ' . $e->getMessage());
    $items = [];
}

$items = dedupe_items_by_key($items);
$title = (string)($author['name'] ?? '作者詳細');
$pageDescription = $title . 'のコミック・BL・TL・読み放題作品一覧です。';
$canonicalUrl = public_url('author.php?id=' . (int)$author['id']);
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '作者一覧', 'url' => public_url('authors.php')],
    ['label' => $title],
]); ?>

<section class="pcf-topic-head">
  <div>
    <h1 class="pcf-hero__title"><?= e($title) ?></h1>
    <?php if (trim((string)($author['ruby'] ?? '')) !== ''): ?>
      <p class="pcf-list-card__meta">読み：<?= e((string)$author['ruby']) ?></p>
    <?php endif; ?>
    <p class="pcf-list-card__meta">関連作品：<?= e(number_format(count($items))) ?>件</p>
  </div>
</section>

<h2 class="pcf-section-title"><?= e($title) ?>の作品</h2>
<?php if ($items !== []): ?>
  <section class="pcf-related-grid pcf-comics-catalog-grid">
    <?php foreach ($items as $item): ?>
      <?php pcf_render_item_card(is_array($item) ? $item : [], 200, true); ?>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('この作者の作品はまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
