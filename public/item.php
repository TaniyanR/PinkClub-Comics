<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/../lib/comics_rankings.php';
require_once __DIR__ . '/partials/public_ui.php';

function comics_item_first_text(mixed $value): string
{
    if (is_string($value) || is_numeric($value)) {
        return trim((string)$value);
    }
    if (!is_array($value)) {
        return '';
    }
    foreach (['name', 'value', 'text', 'title'] as $key) {
        if (isset($value[$key])) {
            $candidate = comics_item_first_text($value[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }
    foreach ($value as $child) {
        $candidate = comics_item_first_text($child);
        if ($candidate !== '') {
            return $candidate;
        }
    }
    return '';
}

function comics_item_named_rows(string $kind, int $itemId): array
{
    $config = [
        'author' => ['relation' => 'item_authors', 'master' => 'authors', 'name' => 'author_name'],
        'genre' => ['relation' => 'item_genres', 'master' => 'genres', 'name' => 'genre_name'],
        'maker' => ['relation' => 'item_makers', 'master' => 'makers', 'name' => 'maker_name'],
        'series' => ['relation' => 'item_series', 'master' => 'series_master', 'name' => 'series_name'],
    ];
    if (!isset($config[$kind]) || $itemId <= 0) {
        return [];
    }

    $row = $config[$kind];
    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT m.id, m.dmm_id, m.name
             FROM ' . $row['relation'] . ' r
             LEFT JOIN ' . $row['master'] . ' m ON m.dmm_id = r.dmm_id
             WHERE r.item_id = :item_id
             ORDER BY m.name ASC'
        );
        $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows !== []) {
            return array_values(array_filter($rows, static fn(array $v): bool => trim((string)($v['name'] ?? '')) !== ''));
        }

        $fallback = db()->prepare(
            'SELECT DISTINCT r.dmm_id, r.' . $row['name'] . ' AS name
             FROM ' . $row['relation'] . ' r
             WHERE r.item_id = :item_id AND TRIM(COALESCE(r.' . $row['name'] . ', "")) <> ""
             ORDER BY r.' . $row['name'] . ' ASC'
        );
        $fallback->bindValue(':item_id', $itemId, PDO::PARAM_INT);
        $fallback->execute();
        return $fallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('comics item relation failed: ' . $kind . ': ' . $e->getMessage());
        return [];
    }
}

function comics_item_labels(int $itemId): array
{
    if ($itemId <= 0) {
        return [];
    }
    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT dmm_id, label_name AS name
             FROM item_labels
             WHERE item_id = :item_id AND TRIM(COALESCE(label_name, "")) <> ""
             ORDER BY label_name ASC'
        );
        $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function comics_item_links(array $rows, string $path): string
{
    $links = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $links[] = '<a href="' . e(public_url($path . '?id=' . $id)) . '">' . e($name) . '</a>';
        } else {
            $links[] = e($name);
        }
    }
    return $links !== [] ? implode('、', $links) : '―';
}

function comics_item_ranking_scope_value(array $rows): string
{
    $first = $rows[0] ?? null;
    if (!is_array($first)) {
        return '';
    }
    $dmmId = trim((string)($first['dmm_id'] ?? ''));
    if ($dmmId !== '') {
        return $dmmId;
    }
    return trim((string)($first['name'] ?? ''));
}

function comics_item_render_ranking(string $heading, array $rows): void
{
    if ($rows === []) {
        return;
    }
    echo '<section class="block pcf-comics-ranking" style="margin-top:24px;">';
    echo '<h2 class="pcf-section-title">' . e($heading) . '</h2>';
    echo '<ol class="pcf-comics-ranking__list" style="margin:0;padding:0;list-style:none;">';
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int)($row['id'] ?? 0);
        $title = trim((string)($row['title'] ?? ''));
        if ($id <= 0 || $title === '') {
            continue;
        }
        echo '<li style="display:grid;grid-template-columns:52px 1fr auto;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #eee;">';
        echo '<strong style="text-align:center;font-size:18px;">' . e((string)($index + 1)) . '</strong>';
        echo '<a href="' . e(public_url('item.php?id=' . $id)) . '">' . e($title) . '</a>';
        echo '<span style="font-size:12px;white-space:nowrap;">' . e(number_format((int)($row['access_count'] ?? 0))) . ' pt</span>';
        echo '</li>';
    }
    echo '</ol>';
    echo '</section>';
}

$id = (int)get('id', 0);
$contentId = trim((string)get('content_id', ''));
if ($contentId === '') {
    $contentId = trim((string)get('cid', ''));
}

$item = false;
try {
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM items i WHERE i.id = :id AND ' . items_product_source_where('i') . ' LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($contentId !== '') {
        $stmt = db()->prepare('SELECT * FROM items i WHERE i.content_id = :content_id AND ' . items_product_source_where('i') . ' LIMIT 1');
        $stmt->bindValue(':content_id', $contentId, PDO::PARAM_STR);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('comics item fetch failed: ' . $e->getMessage());
}

if (!$item) {
    require __DIR__ . '/404.php';
}

$itemId = (int)$item['id'];
$raw = [];
$rawJson = trim((string)($item['raw_json'] ?? ''));
if ($rawJson !== '') {
    $decoded = json_decode($rawJson, true);
    if (is_array($decoded)) {
        $raw = $decoded;
    }
}

$title = pcf_item_title($item);
$packageImage = pcf_item_image($item);
$sampleImages = pcf_pick_sample_image_urls_from_raw($raw);
$sampleImages = array_values(array_slice(array_unique($sampleImages), 0, 24));

$description = comics_item_first_text($raw['comment'] ?? null);
if ($description === '') {
    $description = comics_item_first_text($raw['description'] ?? null);
}
if ($description === '') {
    $description = comics_item_first_text($raw['caption'] ?? null);
}
if ($description === '') {
    $description = comics_item_first_text($raw['story'] ?? null);
}

$authors = comics_item_named_rows('author', $itemId);
$genres = comics_item_named_rows('genre', $itemId);
$makers = comics_item_named_rows('maker', $itemId);
$series = comics_item_named_rows('series', $itemId);
$labels = comics_item_labels($itemId);

$affiliateUrl = trim((string)($item['affiliate_url'] ?? ''));
$affiliateOutUrl = $affiliateUrl !== '' ? public_url('out.php') . '?' . http_build_query(['to' => $affiliateUrl]) : '';
$price = trim((string)($item['price_min_text'] ?? ''));
$releaseDate = trim((string)($item['release_date'] ?? ''));
$deviceText = comics_item_first_text($raw['supportedDevices'] ?? null);
if ($deviceText === '') {
    $deviceText = comics_item_first_text($raw['device'] ?? null);
}

$floorLabel = pcf_comics_floor_label($item);
$floorScope = match ($floorLabel) {
    'BL' => 'bl',
    'TL' => 'tl',
    '読み放題' => 'unlimited',
    default => 'comic',
};

$relatedItems = [];
try {
    $relatedItems = fetch_related_items((string)$item['content_id'], 12);
} catch (Throwable) {
    $relatedItems = [];
}
$relatedItems = dedupe_items_by_key($relatedItems);

$categoryRanking = pcf_comics_scoped_ranking('floor', $floorScope, 'weekly', 10);
$authorRanking = pcf_comics_scoped_ranking('author', comics_item_ranking_scope_value($authors), 'weekly', 10);
$genreRanking = pcf_comics_scoped_ranking('genre', comics_item_ranking_scope_value($genres), 'weekly', 10);
$seriesRanking = pcf_comics_scoped_ranking('series', comics_item_ranking_scope_value($series), 'weekly', 10);

$pageDescription = $description !== '' ? mb_substr(strip_tags($description), 0, 150) : $title . 'の作品情報。作者・ジャンル・シリーズ・価格などを掲載しています。';
$canonicalUrl = public_url('item.php?id=' . $itemId);
$ogImage = $packageImage;
$ogType = 'product';
$productJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $title,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
];
if ($packageImage !== '') {
    $productJsonLd['image'] = $packageImage;
}
if ($price !== '') {
    $numericPrice = preg_replace('/[^0-9.]/', '', $price);
    if (is_string($numericPrice) && $numericPrice !== '') {
        $productJsonLd['offers'] = [
            '@type' => 'Offer',
            'url' => $affiliateUrl !== '' ? $affiliateUrl : $canonicalUrl,
            'priceCurrency' => 'JPY',
            'price' => $numericPrice,
            'availability' => 'https://schema.org/InStock',
        ];
    }
}
$jsonLd = (string)json_encode($productJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => $floorLabel, 'url' => public_url('catalog.php?type=' . $floorScope)],
    ['label' => $title],
]); ?>

<article class="pcf-comics-item">
  <h1 class="pcf-hero__title pcf-item-title"><?= e($title) ?></h1>

  <?php if ($sampleImages !== []): ?>
    <section class="pcf-comics-samples" style="margin:18px 0;">
      <h2 class="pcf-section-title">サンプル画像</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;">
        <?php foreach ($sampleImages as $index => $image): ?>
          <a href="<?= e((string)$image) ?>" target="_blank" rel="noopener noreferrer nofollow">
            <img src="<?= e((string)$image) ?>" alt="<?= e($title) ?> サンプル画像 <?= e((string)($index + 1)) ?>" loading="lazy" style="display:block;width:100%;height:170px;object-fit:contain;background:#fff;">
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($affiliateUrl !== ''): ?>
    <p><a class="pcf-btn" style="display:block;text-align:center;font-weight:700;font-size:18px;padding:12px 14px;" href="<?= e($affiliateOutUrl) ?>" target="_blank" rel="noopener noreferrer sponsored nofollow">FANZAで作品を見る</a></p>
  <?php endif; ?>

  <section class="pcf-detail pcf-item-main">
    <div class="pcf-item-main__media" style="width:min(100%,520px);">
      <?php if ($packageImage !== ''): ?>
        <a href="<?= e($packageImage) ?>" target="_blank" rel="noopener noreferrer">
          <img class="pcf-detail__package pcf-comics-cover" src="<?= e($packageImage) ?>" alt="<?= e($title) ?>" style="display:block;width:100%;height:auto;object-fit:contain;">
        </a>
      <?php endif; ?>
      <?php if ($description !== ''): ?>
        <p><?= nl2br(e($description)) ?></p>
      <?php endif; ?>
    </div>

    <div class="pcf-item-main__info">
      <table style="width:100%;border-collapse:collapse;border:0;color:#000 !important;font-size:13px;">
        <tbody>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">区分</th><td><?= e($floorLabel) ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">作者</th><td><?= comics_item_links($authors, 'author.php') ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">シリーズ</th><td><?= comics_item_links($series, 'series_detail.php') ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">メーカー</th><td><?= comics_item_links($makers, 'maker.php') ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">レーベル</th><td><?= $labels !== [] ? e(implode('、', array_map(static fn(array $row): string => (string)($row['name'] ?? ''), $labels))) : '―' ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">ジャンル</th><td><?= comics_item_links($genres, 'genre.php') ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">配信開始日</th><td><?= $releaseDate !== '' ? e(format_date($releaseDate)) : '―' ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">価格</th><td><?= $price !== '' ? e($price) : '―' ?></td></tr>
          <tr><th style="text-align:left;padding:6px 10px 6px 0;white-space:nowrap;">対応デバイス</th><td><?= $deviceText !== '' ? e($deviceText) : '―' ?></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($affiliateUrl !== ''): ?>
    <p><a class="pcf-btn" style="display:block;text-align:center;font-weight:700;font-size:18px;padding:12px 14px;" href="<?= e($affiliateOutUrl) ?>" target="_blank" rel="noopener noreferrer sponsored nofollow">FANZAで作品を見る</a></p>
  <?php endif; ?>

  <h2 class="pcf-section-title">関連作品</h2>
  <?php if ($relatedItems !== []): ?>
    <section class="pcf-related-grid pcf-item-related-grid">
      <?php foreach ($relatedItems as $related): ?>
        <?php pcf_render_item_card(is_array($related) ? $related : [], 180, true); ?>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <?php pcf_render_empty('関連作品はありません。'); ?>
  <?php endif; ?>

  <?php comics_item_render_ranking($floorLabel . ' 週間人気作品ランキング', $categoryRanking); ?>
  <?php if ($authors !== []): ?>
    <?php comics_item_render_ranking((string)($authors[0]['name'] ?? '作者') . 'の週間人気作品ランキング', $authorRanking); ?>
  <?php endif; ?>
  <?php if ($genres !== []): ?>
    <?php comics_item_render_ranking((string)($genres[0]['name'] ?? 'ジャンル') . 'の週間人気作品ランキング', $genreRanking); ?>
  <?php endif; ?>
  <?php if ($series !== []): ?>
    <?php comics_item_render_ranking((string)($series[0]['name'] ?? 'シリーズ') . 'の週間人気作品ランキング', $seriesRanking); ?>
  <?php endif; ?>
</article>

<?php require __DIR__ . '/partials/footer.php'; ?>
