# NTAG Random Article Redirector

**Turn NFC keychains into a physical "random article" reader for your WordPress site.**

Every tap on the NFC tag serves a different article — no app needed, no website to navigate. Just tap and read.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-REST%20API-21759B?logo=wordpress&logoColor=white)
![NFC](https://img.shields.io/badge/NFC-NTAG215%2F216-00A98F)
![License](https://img.shields.io/badge/License-MIT-green)

---

## The Idea

I run [airevue.cz](https://airevue.cz), a Czech-language AI news publication. I wanted a way to share articles with friends **without them having to visit the website, scroll, or pick something to read**.

The solution: **NFC keychains**. Each keychain contains an NTAG tag programmed with a single URL. When someone taps it with their phone, they get redirected to a random article. Next tap — different article. It's like a physical "I'm Feeling Lucky" button for your blog.

```
┌─────────────┐      ┌──────────────────┐      ┌───────────────┐      ┌─────────────────┐
│  NFC NTAG   │ tap  │  ntag.your.site  │ API  │  WordPress    │  302 │  Random article │
│  keychain   │ ───► │  index.php       │ ───► │  REST API     │ ───► │  on your site   │
└─────────────┘      └──────────────────┘      └───────────────┘      └─────────────────┘
```

## How It Works

1. Phone reads the NFC tag → opens `https://ntag.yoursite.com`
2. PHP script calls WordPress REST API → fetches a pool of published articles
3. Script picks one at random → issues a `302 redirect`
4. User lands directly on the article — every tap is different

The entire logic is a single `index.php` file. No database, no authentication, no dependencies beyond PHP and cURL.

## Why Not `orderby=rand`?

WordPress REST API **does not support `orderby=rand`**. Valid values are: `date`, `id`, `title`, `slug`, `modified`, `author`, `relevance`. Using an unsupported value causes an API error.

Our approach: fetch a pool of 50 articles sorted by date, then pick one randomly in PHP using `array_rand()`. This is actually more reliable — it works regardless of caching plugins (WP Super Cache, LiteSpeed, etc.) that would otherwise cache the API response.

## Quick Start

### 1. Deploy the script

Upload `index.php` and `.htaccess` to your subdomain directory:

```bash
# Example structure on your hosting
/ntag.yoursite.com/
├── index.php
└── .htaccess
```

### 2. Configure

Edit three constants at the top of `index.php`:

```php
define('WP_API_BASE',  'https://yoursite.com/wp-json/wp/v2/posts');  // your WP site
define('FALLBACK_URL', 'https://yoursite.com');                       // fallback if API fails
define('POOL_SIZE', 50);                                              // articles to fetch (max 100)
```

### 3. Verify the REST API works

Open this URL in your browser (replace with your domain):

```
https://yoursite.com/wp-json/wp/v2/posts?per_page=1&_fields=link
```

You should see a JSON response like:
```json
[{"link":"https://yoursite.com/some-article/"}]
```

If you get an error, your REST API might be disabled by a security plugin (Wordfence, iThemes, etc.).

### 4. Program NTAG tags

Write `https://ntag.yoursite.com` as an NDEF URI record onto your NFC tags. Tools:

- **NFC Tools** (iOS/Android) — free, simple
- **Flipper Zero** — write NTAG215/216
- **Proxmark3** — for the hardcore

Recommended tags: **NTAG215** (504 bytes) or **NTAG216** (888 bytes) — plenty of space for a URL.

### 5. Make keychains

Embed the tags in keychains, cards, stickers, or whatever you want to hand out. Get creative.

## Features

- **Single file** — just `index.php`, no framework, no composer
- **No auth required** — reads only public (published) posts via REST API
- **cURL with fallback** — uses cURL primarily, falls back to `file_get_contents` if unavailable
- **Cache-proof randomization** — randomizes in PHP, not in the API query
- **Domain validation** — verifies returned URLs match your domain (prevents open redirect)
- **Anti-cache headers** — ensures browsers and proxies never cache the redirect
- **HTTPS enforcement** — `.htaccess` forces HTTPS even if the tag has an HTTP URL
- **Security headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- **Graceful fallback** — redirects to homepage if API is down or returns no results

## Requirements

- PHP 8.1+ (uses `never` return type and `str_starts_with`)
- cURL extension (recommended) or `allow_url_fopen` enabled
- WordPress site with REST API enabled (default since WP 4.7)
- Apache with `mod_rewrite` (for HTTPS redirect in `.htaccess`)

## File Structure

```
├── index.php        # Main redirect script (the only file that matters)
├── .htaccess        # Apache config: HTTPS, no-cache, security headers
├── README.md        # You are here
└── LICENSE          # MIT
```

## Configuration Reference

| Constant       | Default | Description |
|---------------|---------|-------------|
| `WP_API_BASE` | — | Full URL to your WP REST API posts endpoint |
| `FALLBACK_URL`| — | Where to redirect if API fails |
| `TIMEOUT_SEC` | `5` | HTTP request timeout in seconds |
| `POOL_SIZE`   | `50` | Number of articles to fetch (1–100) |

## Adapting for Other Use Cases

This isn't limited to NFC keychains. The same script works for:

- **QR codes** on stickers, posters, or business cards
- **Bookmark** that gives you a random article each time
- **Slack/Discord bot** command that drops a random article link
- **Email signature** "Read something random from our blog"
- **Any WordPress site** — just change the API URL

## License

MIT — do whatever you want with it.

## Author

**Valentino Hesse**
- Web: [hesse.works](https://www.hesse.works)
- Project: [airevue.cz](https://airevue.cz)

---

*Built because tapping a keychain should feel like opening a fortune cookie, but with actual content.*
