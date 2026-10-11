<?php
/**
 * AUTO NEWS - AI Rewrite Engine (9Router / OpenAI-compatible)
 *
 * Reads AI config from the settings table:
 *   - ai_endpoint  (default: NINEROUTER_URL or http://localhost:20128)
 *   - ai_api_key   (optional)
 *   - ai_model     (chat model id)
 *   - ai_temperature
 *
 * Returns a normalized structured object for one article.
 */

function ai_config() {
    $endpoint = setting('ai_endpoint', getenv('NINEROUTER_URL') ?: 'http://localhost:20128');
    $key = setting('ai_api_key', getenv('NINEROUTER_KEY') ?: '');
    $model = setting('ai_model', getenv('NINEROUTER_MODEL') ?: 'openai/gpt-4o-mini');
    $temp = (float)setting('ai_temperature', '0.7');

    $endpoint = rtrim($endpoint, '/');
    // Allow full path; if it ends with /v1 already keep, else append /v1
    if (!preg_match('#/v[0-9]+/?$#', $endpoint)) {
        $endpoint .= '/v1';
    }

    return [
        'endpoint' => $endpoint,
        'url'      => $endpoint . '/chat/completions',
        'key'      => $key,
        'model'    => $model,
        'temp'     => $temp,
        'lang'     => setting('site_lang', DEFAULT_LANG),
        'site_name'=> setting('site_name', APP_NAME),
    ];
}

/**
 * Build the SEO rewrite prompt. Returns structured JSON string in response.
 */
function build_ai_prompt($item, $cfg) {
    $langName = ($cfg['lang'] === 'en') ? 'English' : 'Indonesian';
    $site     = $cfg['site_name'];

    $system = <<<PROMPT
You are an elite SEO content editor and news rewriter for "$site".
You will rewrite an original news item into a fresh, original, highly-optimized article.

STRICT RULES:
- Must be in {$langName}.
- 100% original wording — do NOT copy the source verbatim.
- Rewrite the headline to be more compelling & keyword-rich but stay factual.
- Write a body of 400–700 words with proper paragraphs and clear subheadings (## ) for readability.
- Add an engaging intro paragraph.
- Include an SEO keyword cluster naturally (do not over-stuff).
- Add an "FAQ" section of 3–5 real questions with short answers.
- Output STRICT JSON only, no markdown fences, no commentary.

JSON shape:
{
  "title": "rewritten headline (max 70 chars)",
  "meta_title": "SEO title tag (max 60 chars)",
  "meta_description": "meta description (max 160 chars)",
  "keywords": "comma,seo,keywords",
  "category": "topic category in {$langName}, e.g. Politik / Ekonomi / Olahraga / Teknologi / Hiburan / Internasional / Kesehatan / Nasional",
  "body": "full article markdown",
  "excerpt": "short intro teaser (max 160 chars)",
  "tags": ["tag1","tag2"],
  "image_alt": "descriptive alt text",
  "faqs": [{"q":"question","a":"answer"}]
}

Category rule: prefer the source category if the RSS already provides one; otherwise choose the single most relevant topic category yourself. Keep it short (1-2 words) and always in {$langName}.
PROMPT;

    $user = json_encode([
        'source_title'       => $item['title'],
        'source_summary'     => mb_substr($item['description'] ?? '', 0, 1200),
        'publish_date'       => $item['date'] ?? null,
        'original_url'       => $item['link'] ?? '',
        'source_category'    => $item['category'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ['system' => $system, 'user' => $user];
}

/**
 * Call the AI chat endpoint and parse the structured JSON result.
 */
function ai_rewrite($item) {
    $cfg = ai_config();
    $prompt = build_ai_prompt($item, $cfg);

    $payload = [
        'model'       => $cfg['model'],
        'temperature' => $cfg['temp'],
        'response_format' => ['type' => 'json_object'],
        'messages'    => [
            ['role' => 'system', 'content' => $prompt['system']],
            ['role' => 'user',   'content' => $prompt['user']],
        ],
    ];

    // Add Authorization header if key present
    $headers = ['Content-Type: application/json'];
    if ($cfg['key']) {
        $headers[] = 'Authorization: Bearer ' . $cfg['key'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $cfg['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 60,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200 || !$body) {
        log_event('error', null, null, "AI call failed HTTP {$http}: " . mb_substr((string)$body, 0, 500));
        return null;
    }

    $json = json_decode($body, true);
    $content = $json['choices'][0]['message']['content'] ?? null;
    if (!$content) {
        log_event('error', null, null, 'AI returned no content');
        return null;
    }

    // Some providers return the json as a string; parse it
    if (is_string($content)) {
        // Strip possible markdown fences
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
        $data = json_decode($content, true);
    } else {
        $data = $content;
    }

    if (!is_array($data) || empty($data['title'])) {
        log_event('error', null, null, 'AI returned malformed JSON: ' . mb_substr((string)$content, 0, 500));
        return null;
    }

    return $data;
}

/**
 * Convert markdown-ish body from AI into safe HTML paragraphs (keeps ## as h2).
 */
function body_to_html($body) {
    $body = trim($body);
    // Split by headings / blank lines
    $lines = preg_split('/\R/', $body);
    $html = '';
    $openP = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            if ($openP) { $html .= '</p>'; $openP = false; }
            continue;
        }
        if (preg_match('/^#{1,6}\s+(.*)$/', $line, $m)) {
            if ($openP) { $html .= '</p>'; $openP = false; }
            $html .= '<h2>' . htmlspecialchars($m[1]) . '</h2>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            $html .= '<li>' . htmlspecialchars($m[1]) . '</li>';
            continue;
        }
        if (!$openP) { $html .= '<p>'; $openP = true; }
        $html .= htmlspecialchars($line) . ' ';
    }
    if ($openP) $html .= '</p>';
    return $html;
}

/**
 * Build FAQPage JSON-LD from faqs array.
 */
function build_faq_json($faqs) {
    if (empty($faqs) || !is_array($faqs)) return null;
    $main = [];
    foreach ($faqs as $f) {
        if (empty($f['q']) || empty($f['a'])) continue;
        $main[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]];
    }
    if (empty($main)) return null;
    return json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
