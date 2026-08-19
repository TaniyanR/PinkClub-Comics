<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/site_settings.php';

function pcf_comics_ranking_period_start(string $period = 'weekly'): string
{
    return match ($period) {
        'daily' => date('Y-m-d 00:00:00'),
        'monthly' => date('Y-m-d 00:00:00', strtotime('-1 month')),
        'yearly' => date('Y-m-d 00:00:00', strtotime('-1 year')),
        default => date('Y-m-d 00:00:00', strtotime('-6 days')),
    };
}

function pcf_comics_scoped_ranking(string $scopeType, string $scopeValue, string $period = 'weekly', int $limit = 10): array
{
    $scopeType = strtolower(trim($scopeType));
    $scopeValue = trim($scopeValue);
    $period = in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true) ? $period : 'weekly';
    $limit = max(1, min(30, $limit));

    if ($scopeValue === '' || !in_array($scopeType, ['floor', 'author', 'genre', 'series'], true)) {
        return [];
    }

    $cacheKey = 'comics.ranking.' . hash('sha256', $scopeType . '|' . mb_strtolower($scopeValue, 'UTF-8') . '|' . $period . '|' . $limit);
    try {
        $cached = json_decode((string)(setting_get($cacheKey, '') ?? ''), true);
        if (is_array($cached)
            && (int)($cached['cached_at'] ?? 0) >= time() - 1800
            && is_array($cached['rows'] ?? null)) {
            return $cached['rows'];
        }
    } catch (Throwable) {
    }

    $joins = '';
    $scopeSql = '';
    $periodStart = pcf_comics_ranking_period_start($period);
    $params = [
        ':page_view_from' => $periodStart,
        ':out_click_from' => $periodStart,
    ];

    if ($scopeType === 'author') {
        $joins = ' INNER JOIN item_authors scope_relation ON scope_relation.item_id = i.id ';
        $scopeSql = ' AND (scope_relation.dmm_id = :scope_dmm OR scope_relation.author_name = :scope_name) ';
        $params[':scope_dmm'] = $scopeValue;
        $params[':scope_name'] = $scopeValue;
    } elseif ($scopeType === 'genre') {
        $joins = ' INNER JOIN item_genres scope_relation ON scope_relation.item_id = i.id ';
        $scopeSql = ' AND (scope_relation.dmm_id = :scope_dmm OR scope_relation.genre_name = :scope_name) ';
        $params[':scope_dmm'] = $scopeValue;
        $params[':scope_name'] = $scopeValue;
    } elseif ($scopeType === 'series') {
        $joins = ' INNER JOIN item_series scope_relation ON scope_relation.item_id = i.id ';
        $scopeSql = ' AND (scope_relation.dmm_id = :scope_dmm OR scope_relation.series_name = :scope_name) ';
        $params[':scope_dmm'] = $scopeValue;
        $params[':scope_name'] = $scopeValue;
    } else {
        $normalized = strtolower($scopeValue);
        if ($normalized === 'unlimited') {
            $scopeSql = ' AND (
                LOWER(COALESCE(i.floor_code, "")) IN ("unlimited", "unlimited_comic")
                OR LOWER(COALESCE(i.service_code, "")) = "unlimited_book"
                OR COALESCE(i.floor_name, "") LIKE "%読み放題%"
            ) ';
        } else {
            $scopeSql = ' AND LOWER(COALESCE(i.floor_code, "")) = :scope_floor ';
            $params[':scope_floor'] = $normalized;
        }
    }

    $sql = 'SELECT DISTINCT i.id, i.content_id, i.title, i.floor_code, i.floor_name,
                   i.service_code, i.release_date, i.image_small, i.image_large,
                   i.image_list, i.price_min_text, i.raw_json,
                   COALESCE(pv.page_view_count, 0) AS page_view_count,
                   COALESCE(oc.out_click_count, 0) AS out_click_count,
                   COALESCE(pv.page_view_count, 0) + (COALESCE(oc.out_click_count, 0) * 3) AS access_count
            FROM items i
            ' . $joins . '
            LEFT JOIN (
                SELECT item_id, COUNT(*) AS page_view_count
                FROM page_views
                WHERE viewed_at >= :page_view_from
                GROUP BY item_id
            ) pv ON pv.item_id = i.id
            LEFT JOIN (
                SELECT item_id, COUNT(*) AS out_click_count
                FROM item_out_click_daily
                WHERE clicked_at >= :out_click_from
                GROUP BY item_id
            ) oc ON oc.item_id = i.id
            WHERE ' . items_product_source_where('i') . '
              AND (COALESCE(pv.page_view_count, 0) > 0 OR COALESCE(oc.out_click_count, 0) > 0)
              ' . $scopeSql . '
            ORDER BY access_count DESC, out_click_count DESC, i.id DESC
            LIMIT ' . $limit;

    $rows = [];
    try {
        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('comics scoped ranking failed: ' . $scopeType . ': ' . $e->getMessage());
        $rows = [];
    }

    try {
        setting_set($cacheKey, json_encode([
            'cached_at' => time(),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    } catch (Throwable) {
    }

    return $rows;
}
