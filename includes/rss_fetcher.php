<?php
/**
 * AUTO NEWS - RSS Fetcher
 * Downloads feed, normalizes items, dedups against existing articles.
 */

function fetch_rss_items($feedUrl, $sourceId = null, $sourceSite = null, $lang = 'id') {
    $raw = http_get($feedUrl, 30);
    if (!$raw) {
        log_event('error', $sourceId, null, "RSS empty: $feedUrl");
        return [];
    }

    $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
    if ($xml === false) {
        log_event('error', $sourceId, null, "RSS parse failed: $feedUrl");
        return [];
    }

    $items = [];
    $channel = $xml->channel ?? null;

    // RSS 2.0: <channel><item>
    if ($channel && isset($channel->item)) {
        $sourceTitle = $sourceSite ?: xml_cdata($channel->title);
        foreach ($channel->item as $item) {
            $items[] = normalize_item($item, $sourceTitle, $sourceId, $lang);
        }
    } elseif (isset($xml->entry)) {
        // Atom: <entry>
        $sourceTitle = $sourceSite ?: xml_cdata($xml->title);
        foreach ($xml->entry as $item) {
            $items[] = normalize_item($item, $sourceTitle, $sourceId, $lang, true);
        }
    }

    return $items;
}

function normalize_item($item, $sourceTitle, $sourceId, $lang, $isAtom = false) {
    if ($isAtom) {
        $title   = (string)($item->title ?? '');
        $link    = (string)($item->link->attributes('href') ?? '');
        $guid    = (string)($item->id ?? '');
        $desc    = strip_tags((string)($item->summary ?? $item->content ?? ''));
        $dateRaw = (string)($item->updated ?? $item->published ?? '');
    } else {
        $title   = xml_cdata($item->title);
        $link    = xml_cdata($item->link);
        $guid    = xml_cdata($item->guid) ?: $link;
        $desc    = xml_cdata($item->description) ?: xml_cdata($item->summary);
        $dateRaw = xml_cdata($item->pubDate) ?: xml_cdata($item->pubDate);
    }

    // Extract first image from description (enclosure or embedded img)
    $image = '';
    if (isset($item->enclosure)) {
        $image = (string)$item->enclosure->attributes('url');
    }
    if (!$image && $desc) {
        if (preg_match('/<img[^>]+src=[\"\']([^\"\']+)[\"\']/i', (string)$item->description, $m)) {
            $image = $m[1];
        }
        $desc = strip_tags($desc);
    }

    $date = $dateRaw ? strtotime($dateRaw) : time();
    if (!$date) $date = time();

    // Strip html remnants in description
    $desc = trim(preg_replace('/\s+/u', ' ', $desc));

    return [
        'title'       => trim($title),
        'link'        => trim($link),
        'guid'        => trim($guid) ?: md5($title . $link),
        'description' => $desc,
        'image'       => resolve_url($image, ''),
        'source_site' => $sourceTitle,
        'source_id'   => $sourceId,
        'lang'        => $lang,
        'date'        => date('Y-m-d H:i:s', $date),
    ];
}

/**
 * Check if a guid already exists -> skip. Returns bool.
 */
function article_guid_exists($guid) {
    if (!$guid) return false;
    $st = db()->prepare('SELECT id FROM articles WHERE guid = ? LIMIT 1');
    $st->execute([$guid]);
    return (bool)$st->fetch();
}

/**
 * Pick candidate title/desc for rewrite. Ensures we only take new guid items.
 */
function collect_new_items($items) {
    $new = [];
    foreach ($items as $it) {
        if ($it['guid'] && article_guid_exists($it['guid'])) continue;
        $new[] = $it;
    }
    return $new;
}
