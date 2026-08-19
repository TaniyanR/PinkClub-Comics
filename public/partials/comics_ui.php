<?php

declare(strict_types=1);

if (!function_exists('pcf_comics_floor_label')) {
    function pcf_comics_floor_label(array $item): string
    {
        $floor = strtolower(trim((string)($item['floor_code'] ?? '')));
        $service = strtolower(trim((string)($item['service_code'] ?? '')));
        $floorName = trim((string)($item['floor_name'] ?? ''));

        if ($floor === 'comic' || str_contains($floorName, 'コミック')) {
            return 'コミック';
        }
        if ($floor === 'bl' || preg_match('/(^|[^a-z])bl([^a-z]|$)/i', $floorName)) {
            return 'BL';
        }
        if ($floor === 'tl' || preg_match('/(^|[^a-z])tl([^a-z]|$)/i', $floorName)) {
            return 'TL';
        }
        if (in_array($floor, ['unlimited', 'unlimited_comic'], true)
            || $service === 'unlimited_book'
            || str_contains($floorName, '読み放題')) {
            return '読み放題';
        }

        return $floorName !== '' ? $floorName : 'コミック';
    }
}

if (!function_exists('pcf_comics_named_values')) {
    function pcf_comics_named_values(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = [];
        $walk = static function (mixed $node) use (&$walk, &$values): void {
            if (is_string($node)) {
                $name = trim($node);
                if ($name !== '') {
                    $values[] = $name;
                }
                return;
            }
            if (!is_array($node)) {
                return;
            }
            if (isset($node['name']) && is_string($node['name']) && trim($node['name']) !== '') {
                $values[] = trim($node['name']);
            } elseif (isset($node['value']) && is_string($node['value']) && trim($node['value']) !== '') {
                $values[] = trim($node['value']);
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($value);

        return array_values(array_unique(array_filter($values, static fn(string $name): bool => $name !== '')));
    }
}

if (!function_exists('pcf_comics_item_authors')) {
    function pcf_comics_item_authors(array $item): array
    {
        $rawJson = trim((string)($item['raw_json'] ?? ''));
        if ($rawJson === '') {
            return [];
        }
        $raw = json_decode($rawJson, true);
        if (!is_array($raw)) {
            return [];
        }
        return pcf_comics_named_values($raw['iteminfo']['author'] ?? []);
    }
}

if (!function_exists('pcf_render_sample_movie_modal')) {
    function pcf_render_sample_movie_modal(): void
    {
        // Comics does not render video sample controls.
    }
}

if (!function_exists('pcf_render_item_card')) {
    function pcf_render_item_card(array $item, int $width = 180, bool $preferFullPackageImage = false): void
    {
        $itemId = (int)($item['id'] ?? 0);
        $contentId = trim((string)($item['content_id'] ?? ''));
        $itemUrl = $itemId > 0
            ? public_url('item.php?id=' . $itemId)
            : public_url('item.php?cid=' . rawurlencode($contentId));

        $title = function_exists('pcf_item_title') ? pcf_item_title($item) : trim((string)($item['title'] ?? ''));
        if ($title === '') {
            $title = 'タイトル未設定';
        }

        $imageUrl = function_exists('pcf_item_image') ? trim(pcf_item_image($item)) : trim((string)($item['image_large'] ?? $item['image_small'] ?? ''));
        if ($preferFullPackageImage) {
            foreach ([(string)($item['image_large'] ?? ''), (string)($item['image_small'] ?? '')] as $candidate) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $imageUrl = $candidate;
                    break;
                }
            }
        }

        $authors = pcf_comics_item_authors($item);
        $authorText = implode('、', array_slice($authors, 0, 3));
        $floorLabel = pcf_comics_floor_label($item);
        $price = trim((string)($item['price_min_text'] ?? ''));
        $releaseDate = trim((string)($item['release_date'] ?? ''));

        $raw = [];
        $rawJson = trim((string)($item['raw_json'] ?? ''));
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        $hasSampleImages = function_exists('pcf_pick_sample_image_urls_from_raw')
            && pcf_pick_sample_image_urls_from_raw($raw) !== [];
        $sampleImagesUrl = $contentId !== '' ? public_url('sample_images.php?content_id=' . rawurlencode($contentId)) : '';

        echo '<article class="pcf-dm-card pcf-comics-card">';
        echo '<div class="pcf-comics-card__badge">' . e($floorLabel) . '</div>';
        echo '<a class="pcf-dm-card__image-link" href="' . e($itemUrl) . '">';
        if ($imageUrl !== '') {
            echo '<img class="pcf-dm-card__image pcf-comics-card__cover" src="' . e($imageUrl) . '" alt="' . e($title) . '" loading="lazy">';
        } else {
            echo '<div class="pcf-dm-card__no-image">No Image</div>';
        }
        echo '</a>';
        echo '<h3 class="pcf-dm-card__title"><a href="' . e($itemUrl) . '">' . e($title) . '</a></h3>';
        if ($authorText !== '') {
            echo '<div class="pcf-list-card__meta">作者：' . e($authorText) . '</div>';
        }
        if ($price !== '') {
            echo '<div class="pcf-list-card__meta">価格：' . e($price) . '</div>';
        }
        if ($releaseDate !== '') {
            echo '<div class="pcf-list-card__meta">配信日：' . e(function_exists('format_date') ? format_date($releaseDate) : $releaseDate) . '</div>';
        }
        echo '<div class="pcf-dm-card__actions">';
        echo '<a class="pcf-dm-card__button" href="' . e($itemUrl) . '">作品を見る</a>';
        if ($hasSampleImages && $sampleImagesUrl !== '') {
            echo '<a class="pcf-dm-card__button" href="' . e($sampleImagesUrl) . '" target="_blank" rel="noopener noreferrer nofollow">サンプル画像</a>';
        }
        echo '</div>';
        echo '</article>';
    }
}
