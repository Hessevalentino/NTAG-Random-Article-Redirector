<?php
/**
 * ntag.airevue.cz – Random article redirect for airevue.cz
 *
 * NFC NTAG keychain → this URL → WordPress REST API → 302 redirect to a random article.
 * No authentication required – reads only published posts (public endpoint wp/v2/posts).
 *
 * Location: ntag.airevue.cz/index.php
 * Author:   Valentino Hesse (https://www.hesse.works)
 * Version:  1.2
 * License:  MIT
 *
 * Note: WordPress REST API does not support orderby=rand (only date, id, title, slug,
 * modified, author, relevance). Randomization is handled purely in PHP from a pool of posts.
 */

// ── Konfigurace ──────────────────────────────────────────────────────────────

define('WP_API_BASE',  'https://airevue.cz/wp-json/wp/v2/posts');
define('FALLBACK_URL', 'https://airevue.cz');
define('TIMEOUT_SEC',  5);

// Kolik článků stáhnout jako pool pro náhodný výběr.
// WP REST API maximum per_page je 100. Čím víc, tím větší variabilita,
// ale taky větší payload. 50 je dobrý kompromis.
define('POOL_SIZE', 50);

// ── Funkce ───────────────────────────────────────────────────────────────────

/**
 * HTTP GET požadavek přes cURL (spolehlivější na WEDOS než file_get_contents).
 *
 * @param  string      $url  Cílová URL
 * @return string|null       Tělo odpovědi, nebo null při chybě / ne-200 odpovědi
 */
function http_get(string $url): ?string
{
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => TIMEOUT_SEC,
                'ignore_errors' => true,
                'header'        => "User-Agent: ntag.airevue.cz/1.2\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return ($body !== false) ? $body : null;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => TIMEOUT_SEC,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_USERAGENT      => 'ntag.airevue.cz/1.2',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        return null;
    }

    return $body;
}

/**
 * Zavolá WordPress REST API, stáhne pool článků a vrátí URL náhodného z nich.
 *
 * @return string|null  URL článku, nebo null při chybě
 */
function get_random_post_url(): ?string
{
    $api_url = WP_API_BASE . '?' . http_build_query([
        'orderby'  => 'date',
        'order'    => 'desc',
        'status'   => 'publish',
        'per_page' => POOL_SIZE,
        '_fields'  => 'link',
    ]);

    $body = http_get($api_url);

    if ($body === null) {
        return null;
    }

    $posts = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($posts) || !is_array($posts)) {
        return null;
    }

    // Vyfiltruj pouze validní položky se správnou doménou
    $valid = [];
    foreach ($posts as $post) {
        if (
            isset($post['link']) &&
            is_string($post['link']) &&
            str_starts_with($post['link'], 'https://airevue.cz')
        ) {
            $valid[] = $post['link'];
        }
    }

    if (empty($valid)) {
        return null;
    }

    // Náhodný výběr v PHP
    return $valid[array_rand($valid)];
}

/**
 * Provede HTTP redirect a ukončí script.
 *
 * @param string $url   Cílová URL
 * @param int    $code  HTTP status kód (výchozí 302)
 */
function redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

// ── Hlavní logika ─────────────────────────────────────────────────────────────

// Zakázat cachování této response (browser i proxy)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Powered-By: ntag.airevue.cz');

$url = get_random_post_url();

if ($url !== null) {
    redirect($url);
} else {
    redirect(FALLBACK_URL);
}
