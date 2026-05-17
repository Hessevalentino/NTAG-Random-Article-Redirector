<?php
/**
 * NTAG Random Article Redirector for WordPress
 *
 * NFC NTAG keychain → this URL → WordPress REST API → 302 redirect to a random article.
 * No authentication required – reads only published posts (public endpoint wp/v2/posts).
 *
 * Location: ntag.yoursite.com/index.php
 * Author:   Valentino Hesse (https://www.hesse.works)
 * Version:  1.2
 * License:  MIT
 *
 * Note: WordPress REST API does not support orderby=rand (only date, id, title, slug,
 * modified, author, relevance). Randomization is handled purely in PHP from a pool of posts.
 */

// ── Configuration ────────────────────────────────────────────────────────────

define('WP_API_BASE',  'https://yoursite.com/wp-json/wp/v2/posts');   // ← change to your WP site
define('FALLBACK_URL', 'https://yoursite.com');                        // ← fallback if API fails
define('TIMEOUT_SEC',  5);

// How many articles to fetch as a pool for random selection.
// WP REST API maximum per_page is 100. More = greater variety,
// but also a larger payload. 50 is a good compromise.
define('POOL_SIZE', 50);

// ── Functions ────────────────────────────────────────────────────────────────

/**
 * HTTP GET request via cURL (more reliable than file_get_contents on shared hosting).
 *
 * @param  string      $url  Target URL
 * @return string|null       Response body, or null on error / non-200 response
 */
function http_get(string $url): ?string
{
    if (!function_exists('curl_init')) {
        // Fallback to file_get_contents if cURL is not available
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => TIMEOUT_SEC,
                'ignore_errors' => true,
                'header'        => "User-Agent: ntag-wp-random-redirect/1.2\r\n",
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
        CURLOPT_USERAGENT      => 'ntag-wp-random-redirect/1.2',
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
 * Call WordPress REST API, fetch a pool of articles and return a random one's URL.
 *
 * Strategy:
 *  1. Request POOL_SIZE articles sorted by date from the API
 *  2. Pick one at random in PHP using array_rand()
 *  3. This guarantees randomness regardless of server-side caching
 *
 * @return string|null  Article URL, or null on error
 */
function get_random_post_url(): ?string
{
    // Extract the domain from FALLBACK_URL for link validation
    $allowed_domain = parse_url(FALLBACK_URL, PHP_URL_SCHEME)
        . '://'
        . parse_url(FALLBACK_URL, PHP_URL_HOST);

    $api_url = WP_API_BASE . '?' . http_build_query([
        'orderby'  => 'date',
        'order'    => 'desc',
        'status'   => 'publish',
        'per_page' => POOL_SIZE,
        '_fields'  => 'link',           // only fetch URLs → minimal payload
    ]);

    $body = http_get($api_url);

    if ($body === null) {
        return null;
    }

    $posts = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($posts) || !is_array($posts)) {
        return null;
    }

    // Filter only valid entries matching the expected domain (prevents open redirect)
    $valid = [];
    foreach ($posts as $post) {
        if (
            isset($post['link']) &&
            is_string($post['link']) &&
            str_starts_with($post['link'], $allowed_domain)
        ) {
            $valid[] = $post['link'];
        }
    }

    if (empty($valid)) {
        return null;
    }

    // Random selection in PHP — works even if API ignores random ordering
    return $valid[array_rand($valid)];
}

/**
 * Perform HTTP redirect and terminate the script.
 *
 * @param string $url   Target URL
 * @param int    $code  HTTP status code (default 302)
 */
function redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

// ── Main logic ───────────────────────────────────────────────────────────────

// Prevent caching of this response (browser + proxy)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Powered-By: ntag-wp-random-redirect');

$url = get_random_post_url();

if ($url !== null) {
    redirect($url);
} else {
    // API failed → redirect to homepage
    redirect(FALLBACK_URL);
}
