<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/contact_page_slug.php';
require_once __DIR__ . '/../../lib/public_counts.php';

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$searchQuery = trim((string)($_GET['q'] ?? ''));
$currentType = strtolower(trim((string)($_GET['type'] ?? '')));

$navItems = [
    ['href' => public_url(''), 'label' => 'TOP', 'type' => ''],
    ['href' => public_url('catalog.php?type=comic'), 'label' => 'コミック', 'type' => 'comic'],
    ['href' => public_url('catalog.php?type=bl'), 'label' => 'BL', 'type' => 'bl'],
    ['href' => public_url('catalog.php?type=tl'), 'label' => 'TL', 'type' => 'tl'],
    ['href' => public_url('catalog.php?type=unlimited'), 'label' => '読み放題', 'type' => 'unlimited'],
    ['href' => public_url('authors.php'), 'label' => '作者一覧', 'type' => ''],
    ['href' => public_url('genres.php'), 'label' => 'ジャンル一覧', 'type' => ''],
    ['href' => public_url('series_list.php'), 'label' => 'シリーズ一覧', 'type' => ''],
    ['href' => public_url('labels.php'), 'label' => 'レーベル一覧', 'type' => ''],
];
$mobileMainItems = $navItems;
$mobileInfoItems = [
    ['href' => public_url('page.php?slug=about'), 'label' => 'サイトについて'],
    ['href' => public_url('page.php?slug=privacy-policy'), 'label' => 'Privacy Policy'],
    ['href' => public_url('page.php?slug=que'), 'label' => 'お問い合わせ'],
];
$sitePostCount = null;

$publicCounts = pcf_public_counts();
$sitePostCount = $publicCounts['posts'];

try {
    $stmt = db()->query('SELECT slug,title FROM fixed_pages WHERE is_published = 1 ORDER BY id ASC');
    $fixedPages = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $excludedSlugs = ['about', 'privacy-policy', CONTACT_PAGE_OLD_SLUG, CONTACT_PAGE_SLUG];
    $excludedTitles = ['サイトについて', 'Privacy Policy', 'お問い合わせ'];
    foreach ($fixedPages as $page) {
        $slug = trim((string)($page['slug'] ?? ''));
        $title = trim((string)($page['title'] ?? ''));
        if ($slug === '' || $title === '') {
            continue;
        }
        if (in_array($slug, $excludedSlugs, true) || in_array($title, $excludedTitles, true)) {
            continue;
        }
        $navItems[] = ['href' => public_url('page.php?slug=' . $slug), 'label' => $title, 'type' => ''];
    }
} catch (Throwable $e) {
}
?>
<details class="site-mobile-menu only-sp">
    <summary class="site-mobile-menu__summary">メニュー</summary>
    <div class="site-mobile-menu__body">
        <div class="site-mobile-menu__group">
            <?php foreach ($mobileMainItems as $item) : ?>
                <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="site-mobile-menu__group">
            <?php if ($sitePostCount !== null): ?><a style="color:#000;">作品数：<strong><?= e(number_format($sitePostCount)) ?></strong></a><?php endif; ?>
            <?php foreach ($mobileInfoItems as $item) : ?>
                <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="site-mobile-menu__search" method="get" action="<?= e(public_url('search.php')) ?>">
            <input class="site-search__input" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="作品・作者を検索" aria-label="作品・作者を検索">
            <button class="site-search__button" type="submit">検索</button>
        </form>
    </div>
</details>
<nav class="site-nav" aria-label="グローバルナビゲーション">
    <?php foreach ($navItems as $index => $item) : ?>
        <?php
        $itemPath = (string)parse_url($item['href'], PHP_URL_PATH);
        $isActive = $path === $itemPath;
        if (($item['type'] ?? '') !== '') {
            $isActive = $isActive && $currentType === (string)$item['type'];
        }
        ?>
        <?php if ($index > 0): ?><span class="site-nav__sep" aria-hidden="true"> | </span><?php endif; ?>
        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
    <?php endforeach; ?>
    <form class="site-search" method="get" action="<?= e(public_url('search.php')) ?>">
        <input class="site-search__input" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="作品・作者を検索" aria-label="作品・作者を検索">
        <button class="site-search__button" type="submit">検索</button>
    </form>
</nav>
