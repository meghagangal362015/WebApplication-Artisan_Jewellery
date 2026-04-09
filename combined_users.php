<?php
/**
 * Combined Users Page
 * Merges users from Company A (this site's database) and every company listed
 * in $companies below (remote JSON APIs). Edit that array to add/change URLs.
 */

// ---------------------------------------------------------------------------
// CONFIG: all companies (local first, then remotes)
// ---------------------------------------------------------------------------
$companies = [
    [
        // Company A — this server’s MySQL (no api_url)
        'label'    => 'Company A (Local)',
        'local_db' => true,
        'api_url' => 'https://mgcodes.com/get_users.php',
        'link_url' => 'https://mgcodes.com/',
        'link_text'=> 'Artisan Jewelry by Megha',
    ],
    [
        'label'     => 'Company B (Geeks\' Consulting)',
        'local_db'  => false,
        'api_url'   => 'http://geekyhub.me/api/users.php',
        'api_url_fallbacks' => [
            'http://geekyhub.me/api/users.php',
        ],
        'link_url'  => 'http://geekyhub.me/',
        'link_text' => 'geekyhub.me',
    ],
    [
        'label'     => 'Company C (Komal Gupta Makeup Studio)',
        'local_db'  => false,
        // 1) Server-side curl (sometimes works). 2) company_c_users.json next to this file (works without CORS — paste API JSON from a browser). 3) Browser fetch (needs CORS on buildinfra nginx/OpenResty, not only PHP).
        'fetch_in_browser'       => true,
        'try_server_fetch_first' => true,
        'curl_ssl_verify'        => false,
        'fallback_json_file'     => 'company_c_users.json',
        // Used if the .json file is missing/unreadable on hosting — same data; update both when Komal’s list changes.
        'embedded_fallback_json' => '[{"id":"2","name":"Alice","email":"alice@gmail.com"},{"id":"1","name":"Mansi","email":"mansi.gupta@kg.com"}]',
        // Stops the red “CORS” browser row — Company C comes from file / embedded (+ optional server curl). Set false if the API gets working CORS later.
        'suppress_browser_fetch' => true,
        'api_url'   => 'https://buildinfra.me/kgmakeupstudio/api/users.php',
        'api_url_fallbacks' => [
            'http://buildinfra.me/kgmakeupstudio/api/users.php',
        ],
        'link_url'  => 'https://buildinfra.me/kgmakeupstudio/',
        'link_text' => 'Komal Gupta Makeup Studio',
    ],
];

// ---------------------------------------------------------------------------
// Helpers: same behaviour for every remote API
// ---------------------------------------------------------------------------

/**
 * Strip UTF-8 BOM / leading whitespace so json_decode succeeds.
 */
function sanitize_json_response_body($raw)
{
    if ($raw === '' || $raw === null) {
        return '';
    }
    $s = (string) $raw;
    if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
        $s = substr($s, 3);
    }
    return ltrim($s);
}

/**
 * True if body looks like HTML (error page, redirect page) rather than JSON.
 */
function response_body_looks_like_html($s)
{
    $t = ltrim((string) $s);
    if ($t === '') {
        return false;
    }
    return $t[0] === '<'
        || stripos($t, '<!DOCTYPE') === 0
        || stripos($t, '<html') !== false;
}

/**
 * Hostinger / CDN "prove you're a browser" pages — PHP cURL cannot pass these.
 */
function response_looks_like_bot_challenge($s)
{
    $l = strtolower((string) $s);
    return strpos($l, 'aes.js') !== false
        || strpos($l, 'browser verification') !== false
        || strpos($l, 'checking your browser') !== false
        || strpos($l, 'ddos-guard') !== false
        || (strpos($l, 'challenge') !== false && strpos($l, '<script') !== false);
}

/**
 * Human-readable explanation when the remote URL returns HTML instead of a JSON API.
 */
function remote_html_explanation($responseBody)
{
    if (response_looks_like_bot_challenge($responseBody)) {
        return 'The remote host returned an anti-bot / JavaScript challenge page (not your API). PHP cannot run that script. '
            . 'Fix on the API host: disable bot protection for /api/ (or whitelist this server’s outbound IP), or host a copy of the user JSON on this server — see fallback_json_file in combined_users.php.';
    }
    return 'The URL returned HTML instead of JSON (wrong path, 404/500 page, or redirect). Fix the remote get_users.php/users.php script or URL.';
}

/**
 * Resolve path relative to this script’s directory.
 */
function resolve_local_json_path($path)
{
    $p = trim((string) $path);
    if ($p === '') {
        return '';
    }
    if ($p[0] === '/' || (strlen($p) > 2 && ($p[1] === ':' && ($p[2] === '\\' || $p[2] === '/')))) {
        return $p;
    }
    return __DIR__ . '/' . ltrim($p, '/');
}

/**
 * Load optional fallback_json_file for a company (same shape as remote API: JSON array of users).
 *
 * @return array{rows: array, raw: string, label: string}|null
 */
function load_company_fallback_json($company)
{
    $fallbackRel = $company['fallback_json_file'] ?? null;
    if ($fallbackRel === null || $fallbackRel === '') {
        return null;
    }
    $candidates = array_unique(array_filter([
        resolve_local_json_path($fallbackRel),
        __DIR__ . '/' . basename($fallbackRel),
    ]));
    foreach ($candidates as $fbPath) {
        if ($fbPath === '' || !is_readable($fbPath)) {
            continue;
        }
        $fileRaw = @file_get_contents($fbPath);
        if ($fileRaw === false || $fileRaw === '') {
            continue;
        }
        $parsed = _parse_fallback_user_json_string($fileRaw);
        if ($parsed !== null) {
            $parsed['label'] = basename($fbPath) . ' (local fallback_json_file)';
            return $parsed;
        }
    }
    return null;
}

/**
 * @return array{rows: array, raw: string, label?: string}|null
 */
function _parse_fallback_user_json_string($fileRaw)
{
    $trim = sanitize_json_response_body($fileRaw);
    // Widest host compatibility — avoid extra json_decode() args
    $quick = json_decode($trim, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($quick)) {
        [$decoded, $fe] = decode_json_from_api_body($fileRaw);
        if ($fe !== null) {
            return null;
        }
        $quick = $decoded;
    }
    $tryRows = json_to_user_rows($quick);
    if (count($tryRows) === 0) {
        return null;
    }
    return [
        'rows'  => $tryRows,
        'raw'   => $fileRaw,
        'label' => '',
    ];
}

/**
 * @return array{rows: array, raw: string, label: string}|null
 */
function load_embedded_company_users_json($company)
{
    $raw = $company['embedded_fallback_json'] ?? null;
    if ($raw === null || $raw === '') {
        return null;
    }
    $parsed = _parse_fallback_user_json_string($raw);
    if ($parsed === null) {
        return null;
    }
    $parsed['label'] = 'embedded in combined_users.php';
    return $parsed;
}

/**
 * From $start (must be '[' or '{'), return the substring that is one balanced JSON value, or null.
 */
function extract_balanced_json_fragment($s, $start)
{
    $len = strlen($s);
    if ($start < 0 || $start >= $len) {
        return null;
    }
    $open = $s[$start];
    if ($open !== '[' && $open !== '{') {
        return null;
    }
    $depth   = 0;
    $inStr   = false;
    $escaped = false;
    for ($i = $start; $i < $len; $i++) {
        $c = $s[$i];
        if ($inStr) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($c === '\\') {
                $escaped = true;
                continue;
            }
            if ($c === '"') {
                $inStr = false;
            }
            continue;
        }
        if ($c === '"') {
            $inStr = true;
            continue;
        }
        if ($c === '[' || $c === '{') {
            $depth++;
            continue;
        }
        if ($c === ']' || $c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($s, $start, $i - $start + 1);
            }
        }
    }
    return null;
}

/**
 * Decode JSON from API body: handle gzip, BOM, then scan for first valid JSON object/array (skips HTML/noise before/after).
 *
 * @return array{0: ?array, 1: ?string} [decoded or null, error message or null]
 */
function decode_json_from_api_body($raw)
{
    if ($raw === '' || $raw === null) {
        return [null, 'empty body'];
    }
    $s = (string) $raw;
    // Raw gzip (misconfigured server or missing Content-Encoding) — json_decode looks like "Syntax error"
    if (strlen($s) > 2 && ord($s[0]) === 0x1f && ord($s[1]) === 0x8b && function_exists('gzdecode')) {
        $inflated = @gzdecode($s);
        if ($inflated !== false && $inflated !== '') {
            $s = $inflated;
        }
    }
    $s    = sanitize_json_response_body($s);
    $html = response_body_looks_like_html($s);

    $decoded = json_decode($s, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Challenge pages embed "[]" / "{}" in JS — do not treat as a valid empty API response
        if (!($html && count($decoded) === 0)) {
            return [$decoded, null];
        }
    }
    $firstErr = json_last_error_msg();

    $max = min(strlen($s), 1048576);
    for ($i = 0; $i < $max; $i++) {
        $c = $s[$i];
        if ($c !== '[' && $c !== '{') {
            continue;
        }
        $frag = extract_balanced_json_fragment($s, $i);
        if ($frag === null || strlen($frag) < 2) {
            continue;
        }
        $decoded = json_decode($frag, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if ($html && count($decoded) === 0) {
                continue;
            }
            return [$decoded, null];
        }
    }

    $hint = response_body_looks_like_html($s) ? (' ' . remote_html_explanation($s)) : '';
    $err  = ($firstErr !== '' && $firstErr !== 'No error') ? $firstErr : 'No valid JSON found';
    return [null, trim($err . $hint)];
}

/**
 * GET a URL via file_get_contents (when cURL is missing or returns nothing).
 */
function fetch_remote_via_stream($url, $timeout_seconds = 15, $ssl_verify_peer = true)
{
    if (!ini_get('allow_url_fopen')) {
        return ['body' => '', 'http_code' => 0, 'errno' => -1, 'error' => 'allow_url_fopen is off', 'via' => 'stream'];
    }
    $ssl = [
        'verify_peer'      => $ssl_verify_peer,
        'verify_peer_name' => $ssl_verify_peer,
    ];
    $ctx = stream_context_create([
        'http' => [
            'timeout'         => $timeout_seconds,
            'follow_location' => 1,
            'header'          => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0 Safari/537.36 CombinedUsers\r\n",
        ],
        'ssl'  => $ssl,
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $ok = $body !== false && $body !== null;
    return [
        'body'      => $ok ? (string) $body : '',
        'http_code' => 0,
        'errno'     => $ok ? 0 : -2,
        'error'     => $ok ? '' : 'file_get_contents failed',
        'via'       => 'stream',
    ];
}

/**
 * GET a URL via cURL. Returns body, http code, and error info.
 * Many hosts block the default PHP/cURL user agent; SSL can fail on outdated CA bundles — we retry once without verify only on SSL errors.
 *
 * @param bool|null $ssl_verify_peer null = try secure first, then insecure on SSL failure; true/false = force
 */
function fetch_remote_curl_once($url, $timeout_seconds, $ssl_verify_peer, $extra_opts = [])
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_seconds);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout_seconds));
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 CombinedUsers');
    $verify = $ssl_verify_peer !== false;
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
    if (!empty($extra_opts['encoding'])) {
        curl_setopt($ch, CURLOPT_ENCODING, '');
    }
    if (!empty($extra_opts['ipv4'])) {
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
    }
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $errstr = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'body'              => ($body !== false && $body !== null) ? $body : '',
        'http_code'         => $http,
        'errno'             => $errno,
        'error'             => $errstr,
        'ssl_verify_used'   => $verify,
        'via'               => 'curl',
    ];
}

/**
 * Full remote fetch: cURL with SSL auto-retry, then gzip-friendly + IPv4 passes, then stream fallback.
 *
 * @param bool|null $ssl_verify_peer null = try secure first, then insecure on SSL failure; true/false = force
 */
function fetch_remote($url, $timeout_seconds = 15, $ssl_verify_peer = null)
{
    if (!function_exists('curl_init')) {
        $verify = $ssl_verify_peer !== false;
        return fetch_remote_via_stream($url, $timeout_seconds, $verify);
    }

    // When SSL verify is explicitly off, still run encoding / IPv4 / stream fallbacks (single plain curl often gets "empty reply")
    $forcedInsecure = ($ssl_verify_peer === false);
    $first            = fetch_remote_curl_once($url, $timeout_seconds, !$forcedInsecure, []);
    if ($first['body'] !== '') {
        return $first;
    }

    if (!$forcedInsecure) {
        $ssl_fail = $ssl_verify_peer === null && $first['errno'] !== 0
            && (stripos($first['error'], 'SSL') !== false || $first['errno'] === 60 || $first['errno'] === 77);
        if ($ssl_fail) {
            $second = fetch_remote_curl_once($url, $timeout_seconds, false, []);
            $second['ssl_retry_insecure'] = true;
            if ($second['body'] !== '') {
                return $second;
            }
            $first = $second;
        }
    }

    $verifyExtras = $forcedInsecure ? false : ($ssl_verify_peer === null ? true : ($ssl_verify_peer !== false));

    foreach ([['encoding' => true], ['encoding' => true, 'ipv4' => true]] as $opts) {
        $r = fetch_remote_curl_once($url, $timeout_seconds, $verifyExtras, $opts);
        if ($r['body'] !== '') {
            $r['curl_extra'] = $opts;
            return $r;
        }
    }

    if (!$forcedInsecure && $ssl_verify_peer === null) {
        $r = fetch_remote_curl_once($url, $timeout_seconds, false, ['encoding' => true, 'ipv4' => true]);
        if ($r['body'] !== '') {
            $r['ssl_retry_insecure'] = true;
            $r['curl_extra'] = ['encoding' => true, 'ipv4' => true];
            return $r;
        }
    }

    $streamVerify = !$forcedInsecure && ($ssl_verify_peer !== false);
    $stream       = fetch_remote_via_stream($url, $timeout_seconds, $streamVerify);
    if ($stream['body'] !== '') {
        return $stream;
    }

    return $first;
}

/**
 * Turn decoded JSON into a flat list of user rows (arrays).
 * Supports: [ {...}, {...} ], { "users": [...] }, { "data": [...] }, single object.
 */
function json_to_user_rows($decoded)
{
    $rows = [];
    if (!is_array($decoded)) {
        return $rows;
    }
    if (isset($decoded['users']) && is_array($decoded['users'])) {
        $rows = $decoded['users'];
    } elseif (isset($decoded['results']) && is_array($decoded['results'])) {
        $rows = $decoded['results'];
    } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
        $rows = $decoded['items'];
    } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
        $rows = $decoded['data'];
    } elseif (isset($decoded[0]) || $decoded === []) {
        $rows = $decoded;
    } elseif (isset($decoded['id']) || isset($decoded['name']) || isset($decoded['email'])) {
        $rows = [$decoded];
    }
    return $rows;
}

/**
 * Map odd API field names to id / name / email for the table.
 */
function normalize_user_row($user)
{
    if (!is_array($user)) {
        return [];
    }
    return [
        'id'    => $user['id'] ?? $user['user_id'] ?? '-',
        'name'  => $user['name'] ?? $user['full_name'] ?? $user['username'] ?? '',
        'email' => $user['email'] ?? $user['email_address'] ?? '',
    ];
}

// ---------------------------------------------------------------------------
// Collect users from every source
// ---------------------------------------------------------------------------
$all_users = [];
$remote_fetch_log   = []; // for ?debug_sources=1
$source_warnings    = []; // always shown: remote sources that returned no rows
$browser_fetch_jobs = []; // loaded via JS (fetch API) — see page script

require_once __DIR__ . '/db.php';

foreach ($companies as $company) {
    $label = $company['label'] ?? 'Unknown';

    try {
        if (!empty($company['local_db'])) {
            // Local database (Company A)
            if (!$conn instanceof mysqli) {
                $source_warnings[] = $label . ': database connection is not available.';
                continue;
            }
            if ($conn->connect_error) {
                $source_warnings[] = $label . ': database connection failed — ' . $conn->connect_error;
                continue;
            }
            $sql    = 'SELECT id, name, email FROM users';
            $result = $conn->query($sql);
            if ($result === false) {
                $source_warnings[] = $label . ': query failed — ' . $conn->error;
                continue;
            }
            while ($row = $result->fetch_assoc()) {
                $row['source'] = $label;
                $all_users[]   = $row;
            }
            continue;
        }

        // Remote: same flow for Company B, Company C, and any future entry
        $api_url = $company['api_url'] ?? '';
        if ($api_url === '') {
            continue;
        }

        $sslPref = $company['curl_ssl_verify'] ?? null; // null = auto (secure + SSL retry), true/false to force
        $urls    = array_values(array_filter(array_merge(
            [$api_url],
            array_map('trim', $company['api_url_fallbacks'] ?? [])
        )));

        if (!empty($company['fetch_in_browser'])) {
            // A) Local JSON file, then embedded string in this file (always works when uploaded).
            $fbBr = load_company_fallback_json($company);
            if ($fbBr === null) {
                $fbBr = load_embedded_company_users_json($company);
            }
            if ($fbBr !== null) {
                $remote_fetch_log[] = [
                    'label'      => $label,
                    'url_tried'  => $urls,
                    'url_used'   => $fbBr['label'],
                    'http_code'  => 0,
                    'errno'      => 0,
                    'error'      => '',
                    'via'        => 'local_file',
                    'bytes'      => strlen($fbBr['raw']),
                    'ssl_retry'  => false,
                    'json_error' => null,
                    'looks_html' => false,
                    'rows'       => count($fbBr['rows']),
                    'note'       => 'Loaded from fallback_json_file (no CORS). Update file when the list changes.',
                ];
                foreach ($fbBr['rows'] as $user) {
                    $user = normalize_user_row($user);
                    if ($user === []) {
                        continue;
                    }
                    $user['source'] = $label;
                    $all_users[]    = $user;
                }
                continue;
            }

            // Explain why fallback didn’t load (missing file vs invalid JSON)
            $fbRel = $company['fallback_json_file'] ?? '';
            if ($fbRel !== '') {
                $fbAbs = resolve_local_json_path($fbRel);
                if ($fbAbs === '' || !file_exists($fbAbs)) {
                    $source_warnings[] = $label . ': missing fallback file "' . basename($fbRel)
                        . '" — upload it next to combined_users.php (expected: ' . $fbAbs . ').';
                } elseif (!is_readable($fbAbs)) {
                    $source_warnings[] = $label . ': cannot read fallback file (permissions?) — ' . $fbAbs;
                } else {
                    $source_warnings[] = $label . ': fallback file exists but JSON has no user rows or is invalid — fix '
                        . basename($fbRel) . ' (must be like [{"id":"1","name":"…","email":"…"}, …]).';
                }
            }

            // B) Server-side curl (often returns bot HTML from buildinfra — don’t rely on it).
            if (!empty($company['try_server_fetch_first'])) {
                $fetchSrv = null;
                $respSrv  = '';
                $urlSrv   = '';
                foreach ($urls as $tryUrl) {
                    if ($tryUrl === '') {
                        continue;
                    }
                    $fetchSrv = fetch_remote($tryUrl, 15, $sslPref);
                    $respSrv  = sanitize_json_response_body($fetchSrv['body']);
                    if ($respSrv !== '') {
                        $urlSrv = $tryUrl;
                        break;
                    }
                }
                if ($respSrv !== '') {
                    [$decSrv, $errSrv] = decode_json_from_api_body($respSrv);
                    $rowsSrv           = json_to_user_rows($decSrv ?? []);
                    $htmlSrv           = response_body_looks_like_html($respSrv);
                    if ($htmlSrv && $errSrv === null && count($rowsSrv) === 0) {
                        $errSrv = remote_html_explanation($respSrv);
                    }
                    if ($errSrv === null && count($rowsSrv) > 0) {
                        $remote_fetch_log[] = [
                            'label'      => $label,
                            'url_tried'  => $urls,
                            'url_used'   => $urlSrv,
                            'http_code'  => $fetchSrv['http_code'],
                            'errno'      => $fetchSrv['errno'],
                            'error'      => $fetchSrv['error'],
                            'via'        => $fetchSrv['via'] ?? 'curl',
                            'bytes'      => strlen($respSrv),
                            'ssl_retry'  => !empty($fetchSrv['ssl_retry_insecure']),
                            'json_error' => null,
                            'looks_html' => $htmlSrv,
                            'rows'       => count($rowsSrv),
                            'note'       => 'Server-side fetch succeeded.',
                        ];
                        foreach ($rowsSrv as $user) {
                            $user = normalize_user_row($user);
                            if ($user === []) {
                                continue;
                            }
                            $user['source'] = $label;
                            $all_users[]    = $user;
                        }
                        continue;
                    }
                }
            }

            // C) Browser fetch (only if not suppressed — needs CORS on buildinfra).
            if (!empty($company['suppress_browser_fetch'])) {
                $remote_fetch_log[] = [
                    'label'      => $label,
                    'url_tried'  => $urls,
                    'url_used'   => '(browser disabled — suppress_browser_fetch)',
                    'http_code'  => 0,
                    'errno'      => 0,
                    'error'      => '',
                    'via'        => 'none',
                    'bytes'      => 0,
                    'ssl_retry'  => false,
                    'json_error' => null,
                    'rows'       => 0,
                    'note'       => 'Update company_c_users.json on the server, or set suppress_browser_fetch => false once CORS works.',
                ];
                continue;
            }
            $browser_fetch_jobs[] = [
                'label' => $label,
                'urls'  => $urls,
            ];
            $remote_fetch_log[] = [
                'label'      => $label,
                'url_tried'  => $urls,
                'url_used'   => '(visitor browser — fetch() API)',
                'http_code'  => 0,
                'errno'      => 0,
                'error'      => '',
                'via'        => 'browser',
                'bytes'      => 0,
                'ssl_retry'  => false,
                'json_error' => null,
                'rows'       => null,
                'note'       => 'Rows after JS. If empty: add company_c_users.json on server OR friend fixes CORS at nginx (OpenResty) for /api/.',
            ];
            continue;
        }

        $fetch     = null;
        $url_used  = '';
        $response  = '';
        foreach ($urls as $tryUrl) {
            if ($tryUrl === '') {
                continue;
            }
            $fetch = fetch_remote($tryUrl, 15, $sslPref);
            $response = sanitize_json_response_body($fetch['body']);
            if ($response !== '') {
                $url_used = $tryUrl;
                break;
            }
        }

        if ($response === '') {
            $last = $fetch ?? ['http_code' => 0, 'errno' => 0, 'error' => 'no attempt', 'ssl_retry_insecure' => false];
            $fb   = load_company_fallback_json($company);
            if ($fb === null) {
                $remote_fetch_log[] = [
                    'label'      => $label,
                    'url_tried'  => $urls,
                    'url_used'   => '',
                    'http_code'  => $last['http_code'],
                    'errno'      => $last['errno'],
                    'error'      => $last['error'],
                    'via'        => $last['via'] ?? 'curl',
                    'bytes'      => 0,
                    'ssl_retry'  => !empty($last['ssl_retry_insecure']),
                    'json_error' => null,
                    'rows'       => 0,
                ];
                $source_warnings[] = $label . ': no response from API (tried ' . count($urls) . ' URL(s)). Last cURL: '
                    . ($last['error'] !== '' ? $last['error'] : ('HTTP ' . (string) $last['http_code'] . ' errno ' . (string) $last['errno']));
                continue;
            }
            $rows           = $fb['rows'];
            $jsonErr        = null;
            $response       = $fb['raw'];
            $url_used       = $fb['label'];
            $responseIsHtml = false;
        } else {
            [$decoded, $jsonErr] = decode_json_from_api_body($response);
            $rows                = json_to_user_rows($decoded ?? []);
            $responseIsHtml      = response_body_looks_like_html($response);

            if ($responseIsHtml && $jsonErr === null && count($rows) === 0) {
                $jsonErr = remote_html_explanation($response);
            }

            if ($jsonErr !== null || count($rows) === 0) {
                $fb = load_company_fallback_json($company);
                if ($fb !== null) {
                    $rows           = $fb['rows'];
                    $jsonErr        = null;
                    $response       = $fb['raw'];
                    $url_used       = $fb['label'];
                    $responseIsHtml = false;
                }
            }
        }

        $remote_fetch_log[] = [
            'label'      => $label,
            'url_tried'  => $urls,
            'url_used'   => $url_used,
            'http_code'  => $fetch['http_code'],
            'errno'      => $fetch['errno'],
            'error'      => $fetch['error'],
            'via'        => $fetch['via'] ?? 'curl',
            'bytes'      => strlen($response),
            'ssl_retry'  => !empty($fetch['ssl_retry_insecure']),
            'json_error' => $jsonErr,
            'looks_html' => $responseIsHtml,
            'rows'       => count($rows),
        ];

        if ($jsonErr !== null) {
            $source_warnings[] = $label . ': ' . $jsonErr;
        } elseif (count($rows) === 0) {
            $source_warnings[] = $label . ': response was not a recognized user list (0 rows). First 120 chars: '
                . substr($response, 0, 120);
        }

        foreach ($rows as $user) {
            $user = normalize_user_row($user);
            if ($user === []) {
                continue;
            }
            $user['source'] = $label;
            $all_users[]    = $user;
        }
    } catch (Throwable $e) {
        $source_warnings[] = $label . ': unexpected error — ' . $e->getMessage();
        if (function_exists('error_log')) {
            error_log('combined_users.php [' . $label . ']: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    try {
        $conn->close();
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('combined_users.php: db close — ' . $e->getMessage());
        }
    }
}

// Build intro sentence with optional links (skip empty link_url)
$intro_parts = [];
foreach ($companies as $c) {
    $lab = htmlspecialchars($c['label'] ?? '');
    $href = trim($c['link_url'] ?? '');
    $text = trim($c['link_text'] ?? '');
    if ($href !== '' && $text !== '') {
        $intro_parts[] = $lab . ' (<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener">' . htmlspecialchars($text) . '</a>)';
    } else {
        $intro_parts[] = $lab;
    }
}
$intro_sentence = 'Users from ' . implode(', ', $intro_parts) . '.';

// Exact origin visitors use (CORS on the remote API must allow this string, or use *)
$combined_users_page_origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '');

$unique_sources = [];
foreach ($all_users as $u) {
    $s = (string)($u['source'] ?? '');
    if ($s !== '' && !in_array($s, $unique_sources, true)) {
        $unique_sources[] = $s;
    }
}
if (!empty($browser_fetch_jobs) && is_array($browser_fetch_jobs)) {
    foreach ($browser_fetch_jobs as $job) {
        $lab = (string)($job['label'] ?? '');
        if ($lab !== '' && !in_array($lab, $unique_sources, true)) {
            $unique_sources[] = $lab;
        }
    }
}
sort($unique_sources, SORT_NATURAL | SORT_FLAG_CASE);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combined Users | Artisan Jewelry by Megha</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        nav a.active { background: rgba(255, 255, 255, 0.22); color: #e8c9a8; font-weight: 600; pointer-events: none; }
        .combined-card {
            background: #fff;
            border: 1px solid #e8ddd2;
            border-radius: 16px;
            padding: 1.75rem 2rem 2rem;
            margin: 0 auto 2.5rem;
            max-width: 1000px;
            box-shadow: 0 8px 32px rgba(92, 46, 66, 0.1);
        }
        .combined-card .combined-intro { color: #5a4a4a; font-size: 1.1rem; margin-bottom: 1.25rem; line-height: 1.65; }
        .combined-card .combined-intro a { color: #7d3c5c; font-weight: 600; }
        .combined-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.25rem;
            padding: 0.85rem 1.1rem;
            background: linear-gradient(135deg, rgba(92, 46, 66, 0.06) 0%, rgba(125, 60, 92, 0.08) 100%);
            border-radius: 10px;
            border: 1px solid #e8ddd2;
        }
        .combined-stats strong { color: #5c2e42; font-size: 1.35rem; }
        .combined-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .combined-tab {
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border: 1px solid #d4c4b8;
            border-radius: 999px;
            background: #fdf8f3;
            color: #5c2e42;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .combined-tab:hover { background: #fff; border-color: #7d3c5c; color: #7d3c5c; }
        .combined-tab.active { background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%); color: #fff; border-color: #5c2e42; }
        .combined-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #e8ddd2; }
        .combined-table { width: 100%; border-collapse: collapse; min-width: 480px; background: #fff; }
        .combined-table thead th {
            text-align: left;
            padding: 0.85rem 1rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%);
            font-size: 0.88rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .combined-table tbody td { padding: 0.75rem 1rem; border-bottom: 1px solid #f0ebe5; color: #3d3535; }
        .combined-table tbody tr.combined-user-row:nth-child(even) { background: #fdf9f5; }
        .combined-table tbody tr.combined-user-row:hover { background: rgba(125, 60, 92, 0.06); }
        .combined-table .source { font-size: 0.92em; color: #7d3c5c; font-weight: 600; }
        .combined-table .combined-seq { text-align: right; width: 3rem; color: #6b5d5d; font-variant-numeric: tabular-nums; font-weight: 600; }
        .browser-load-note { font-size: 0.95rem; color: #4a4242; line-height: 1.55; margin: 1rem 0; padding: 1rem; background: #f8f4ef; border-radius: 10px; border: 1px solid #e8ddd2; }
        .browser-fetch-err { color: #8b2942 !important; background: #fce8ec !important; font-weight: 600; }
        .browser-fetch-err td { border-bottom-color: #f5d0d8; }
        .debug-sources { margin: 2rem auto 0; max-width: 1000px; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 6px; overflow-x: auto; }
        .debug-sources h2 { font-size: 14px; margin: 0 0 8px; color: #fff; }
        .source-warn { background: #fff8e8; border: 1px solid #e8c9a8; color: #5c3d20; padding: 12px 14px; border-radius: 10px; margin: 1rem 0; font-size: 0.95rem; }
        .source-warn ul { margin: 8px 0 0 18px; padding: 0; }
        #combined-users-placeholder td { font-style: italic; color: #7d6a62; }
        .combined-empty-row td { text-align: center; padding: 2rem; color: #7d6a62; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">Artisan Jewelry <span>by Megha</span></div>
            <nav>
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="products.html">Products & Services</a></li>
                    <li><a href="news.html">News</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="combined_users.php" class="active" aria-current="page">Combined Users</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="page-title">
        <div class="container">
            <h1>Combined Users</h1>
            <p style="margin-top:0.75rem;opacity:0.95;font-size:1.05rem;">All companies in one place — filter by source below.</p>
        </div>
    </section>

    <main>
    <div class="container">
    <!-- combined_users build: 2026-04-08 seq tabs; no seq hint line -->
    <div class="combined-card">
    <p class="combined-intro"><?php echo $intro_sentence; ?></p>

    <div class="combined-stats" aria-live="polite">
        <span><strong id="combined-user-count"><?php echo (int) count($all_users); ?></strong> users in this table</span>
    </div>

    <?php if (count($unique_sources) > 0): ?>
    <div class="combined-tabs" role="tablist" aria-label="Filter by company source">
        <button type="button" class="combined-tab active" data-filter="all" role="tab" aria-selected="true">All sources</button>
        <?php foreach ($unique_sources as $src): ?>
        <button type="button" class="combined-tab" data-filter="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="false"><?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['debug_combined'])): ?>
    <div class="source-warn" style="background:#e3f2fd;border-color:#2196f3;color:#0d47a1;">
        <strong>debug_combined=1</strong> — script directory: <code><?php echo htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8'); ?></code><br>
        Company C fallback path: <code><?php $dp = resolve_local_json_path('company_c_users.json'); echo htmlspecialchars($dp, ENT_QUOTES, 'UTF-8'); ?></code>
        — exists: <?php echo $dp !== '' && file_exists($dp) ? 'yes' : 'no'; ?>,
        readable: <?php echo $dp !== '' && is_readable($dp) ? 'yes' : 'no'; ?>
        <?php
        if ($dp !== '' && is_readable($dp)) {
            $dbgRaw = @file_get_contents($dp);
            [$dbgDec, $dbgErr] = decode_json_from_api_body($dbgRaw !== false ? $dbgRaw : '');
            $dbgRows = json_to_user_rows($dbgDec ?? []);
            echo '<br>JSON decode error: ' . htmlspecialchars($dbgErr ?? 'none', ENT_QUOTES, 'UTF-8');
            echo '; parsed user rows: ' . (int) count($dbgRows);
        }
        ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($source_warnings)): ?>
    <div class="source-warn" role="status">
        <strong>Some remote sources did not load users:</strong>
        <ul>
            <?php foreach ($source_warnings as $w): ?>
                <li><?php echo htmlspecialchars($w); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($browser_fetch_jobs)): ?>
    <p class="browser-load-note"><strong>Company C:</strong> If rows still fail in the browser, your friend must add CORS in <strong>nginx / OpenResty</strong> (Hostinger often ignores PHP-only headers). Meanwhile you can upload <code>company_c_users.json</code> next to this file (copy JSON from the API in a browser) — it loads <em>before</em> the browser request. Exact origin for CORS: <strong><?php echo htmlspecialchars($combined_users_page_origin, ENT_QUOTES, 'UTF-8'); ?></strong>. <code>?debug_browser=1</code> adds console detail.</p>
    <?php endif; ?>

    <div class="combined-table-wrap">
    <table class="combined-table">
        <thead>
            <tr>
                <th class="combined-seq" scope="col" id="combined-seq-th" title="Combined order 1…N when all sources are shown; per-source 1…n when one company tab is selected">Seq</th>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col">Source</th>
            </tr>
        </thead>
        <tbody id="combined-users-tbody">
            <?php if (empty($all_users) && empty($browser_fetch_jobs)): ?>
                <tr class="combined-empty-row" data-role="empty">
                    <td colspan="4">No users found.</td>
                </tr>
            <?php else: ?>
                <?php if (empty($all_users) && !empty($browser_fetch_jobs)): ?>
                <tr id="combined-users-placeholder" data-role="loading"><td colspan="4">Loading remote users in your browser…</td></tr>
                <?php endif; ?>
                <?php
                $row_seq = 0;
                $source_seq_by_label = [];
                foreach ($all_users as $user):
                    $row_seq++;
                    $srcAttr = (string)($user['source'] ?? 'Unknown');
                    if (!isset($source_seq_by_label[$srcAttr])) {
                        $source_seq_by_label[$srcAttr] = 0;
                    }
                    $source_seq_by_label[$srcAttr]++;
                    $sourceSeq = $source_seq_by_label[$srcAttr];
                    ?>
                    <tr class="combined-user-row" data-source="<?php echo htmlspecialchars($srcAttr, ENT_QUOTES, 'UTF-8'); ?>" data-global-seq="<?php echo (int) $row_seq; ?>" data-source-seq="<?php echo (int) $sourceSeq; ?>">
                        <td class="combined-seq"><?php echo (int) $row_seq; ?></td>
                        <td><?php echo htmlspecialchars((string)($user['name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)($user['email'] ?? '')); ?></td>
                        <td class="source"><?php echo htmlspecialchars($srcAttr, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div><?php /* .combined-card */ ?>

    <script>
    (function () {
        var currentFilter = 'all';
        var tbody = document.getElementById('combined-users-tbody');
        function applyFilter() {
            if (!tbody) return;
            tbody.querySelectorAll('tr').forEach(function (tr) {
                var role = tr.getAttribute('data-role');
                if (role === 'loading' || role === 'empty') {
                    tr.style.display = (currentFilter === 'all') ? '' : 'none';
                    return;
                }
                if (!tr.hasAttribute('data-source')) {
                    return;
                }
                var src = tr.getAttribute('data-source');
                if (currentFilter === 'all') {
                    tr.style.display = '';
                } else {
                    tr.style.display = (src === currentFilter) ? '' : 'none';
                }
            });
        }
        function syncGlobalSeqFromDom() {
            if (!tbody) return;
            var i = 0;
            tbody.querySelectorAll('tr.combined-user-row').forEach(function (tr) {
                i++;
                tr.setAttribute('data-global-seq', String(i));
            });
        }
        function syncSourceSeqFromDom() {
            if (!tbody) return;
            var bySource = {};
            tbody.querySelectorAll('tr.combined-user-row').forEach(function (tr) {
                var src = tr.getAttribute('data-source') || '';
                bySource[src] = (bySource[src] || 0) + 1;
                tr.setAttribute('data-source-seq', String(bySource[src]));
            });
        }
        function renumberVisibleUserRows() {
            if (!tbody) return;
            if (currentFilter === 'all') {
                tbody.querySelectorAll('tr.combined-user-row').forEach(function (tr) {
                    var cell = tr.querySelector('td.combined-seq');
                    var g = tr.getAttribute('data-global-seq');
                    if (cell && g) {
                        cell.textContent = g;
                    }
                });
                return;
            }
            tbody.querySelectorAll('tr.combined-user-row').forEach(function (tr) {
                if (tr.style.display === 'none') {
                    return;
                }
                var cell = tr.querySelector('td.combined-seq');
                var s = tr.getAttribute('data-source-seq');
                if (cell && s) {
                    cell.textContent = s;
                }
            });
        }
        function recount() {
            if (!tbody) return;
            var el = document.getElementById('combined-user-count');
            if (!el) {
                return;
            }
            var total = tbody.querySelectorAll('tr.combined-user-row').length;
            if (currentFilter === 'all') {
                el.textContent = String(total);
                return;
            }
            var vis = 0;
            tbody.querySelectorAll('tr.combined-user-row').forEach(function (tr) {
                if (tr.style.display !== 'none') {
                    vis++;
                }
            });
            el.textContent = String(vis);
        }
        function setActiveTab(activeBtn) {
            document.querySelectorAll('.combined-tab').forEach(function (b) {
                var on = b === activeBtn;
                b.classList.toggle('active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        document.querySelectorAll('.combined-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentFilter = btn.getAttribute('data-filter') || 'all';
                setActiveTab(btn);
                applyFilter();
                recount();
                renumberVisibleUserRows();
            });
        });
        window.__combinedRefresh = function () {
            syncGlobalSeqFromDom();
            syncSourceSeqFromDom();
            applyFilter();
            recount();
            renumberVisibleUserRows();
        };
        applyFilter();
        recount();
        renumberVisibleUserRows();
    })();
    </script>

    <?php if (!empty($browser_fetch_jobs)): ?>
    <script type="application/json" id="combined-users-browser-jobs"><?php
        echo json_encode($browser_fetch_jobs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES);
    ?></script>
    <script>
    (function () {
        var CU_DEBUG = <?php echo !empty($_GET['debug_browser']) ? 'true' : 'false'; ?>;
        function logWarn(msg, detail) {
            if (console.warn) console.warn('[Combined Users]', msg, detail || '');
        }
        function logErr(msg, err) {
            if (console.error) console.error('[Combined Users]', msg, err || '');
        }
        var el = document.getElementById('combined-users-browser-jobs');
        if (!el || !el.textContent) {
            logErr('Missing embedded browser-fetch job config');
            return;
        }
        var jobs;
        try { jobs = JSON.parse(el.textContent); } catch (e) {
            logErr('Invalid job JSON embedded in page', e);
            return;
        }
        var tbody = document.getElementById('combined-users-tbody');
        if (!tbody || !jobs.length) return;

        function parseJsonLoose(text) {
            if (!text) return null;
            var s = text.replace(/^\uFEFF/, '').trim();
            if (s.charAt(0) === '<') return null;
            try { return JSON.parse(s); } catch (e1) {}
            var p = s.indexOf('[');
            if (p >= 0) { try { return JSON.parse(s.slice(p)); } catch (e2) {} }
            var o = s.indexOf('{');
            if (o >= 0) { try { return JSON.parse(s.slice(o)); } catch (e3) {} }
            return null;
        }
        function jsonToRows(d) {
            if (!d || typeof d !== 'object') return [];
            if (Array.isArray(d.users)) return d.users;
            if (Array.isArray(d.results)) return d.results;
            if (Array.isArray(d.items)) return d.items;
            if (Array.isArray(d.data)) return d.data;
            if (Array.isArray(d)) return d;
            if (d.id != null || d.name != null || d.email != null) return [d];
            return [];
        }
        function norm(u) {
            if (!u || typeof u !== 'object') return null;
            return {
                id: u.id != null ? String(u.id) : (u.user_id != null ? String(u.user_id) : '-'),
                name: String(u.name != null ? u.name : (u.full_name != null ? u.full_name : (u.username != null ? u.username : ''))),
                email: String(u.email != null ? u.email : (u.email_address != null ? u.email_address : ''))
            };
        }
        function esc(s) {
            var t = document.createTextNode(s);
            var span = document.createElement('span');
            span.appendChild(t);
            return span.innerHTML;
        }
        function appendRow(name, email, source) {
            var tr = document.createElement('tr');
            tr.className = 'combined-user-row';
            tr.setAttribute('data-source', source);
            tr.innerHTML = '<td class="combined-seq"></td><td>' + esc(name) + '</td><td>' + esc(email) + '</td><td class="source">' + esc(source) + '</td>';
            tbody.appendChild(tr);
        }
        function errRow(label, msg) {
            var tr = document.createElement('tr');
            tr.className = 'browser-fetch-err';
            tr.setAttribute('data-source', label);
            tr.innerHTML = '<td colspan="4">' + esc(label + ': ' + msg) + '</td>';
            tbody.appendChild(tr);
            if (window.__combinedRefresh) window.__combinedRefresh();
        }

        function removeLoadingPlaceholder() {
            var row = document.getElementById('combined-users-placeholder');
            if (row && row.parentNode) row.parentNode.removeChild(row);
        }

        jobs.forEach(function (job) {
            var label = job.label || 'Unknown';
            var urls = job.urls || [];
            (function tryUrl(i, lastErr) {
                if (i >= urls.length) {
                    var origin = (typeof location !== 'undefined' && location.origin) ? location.origin : '';
                    var lastMsg = lastErr && lastErr.message ? lastErr.message : (lastErr ? String(lastErr) : '');
                    var msg = 'Could not load after trying ' + urls.length + ' URL(s). ';
                    msg += 'API must send Access-Control-Allow-Origin: ' + origin + ' (or *). ';
                    if (lastMsg) msg += 'Last error: ' + lastMsg + '. ';
                    msg += 'Check Network tab for users.php — response must be JSON with CORS headers.';
                    errRow(label, msg);
                    logErr(label + ' — all URLs failed', lastErr || new Error('no URLs or all attempts failed'));
                    removeLoadingPlaceholder();
                    return;
                }
                var url = urls[i];
                fetch(url, { method: 'GET', mode: 'cors', credentials: 'omit', cache: 'no-store' })
                    .then(function (r) {
                        if (!r.ok) {
                            var httpErr = new Error('HTTP ' + r.status + ' ' + (r.statusText || '') + ' — ' + url);
                            if (CU_DEBUG) logWarn('Bad status', httpErr.message);
                            throw httpErr;
                        }
                        return r.text();
                    })
                    .then(function (text) {
                        var d = parseJsonLoose(text);
                        if (!d) {
                            var pe = new Error('Response was not JSON (often HTML / bot page). URL: ' + url);
                            if (CU_DEBUG) logWarn('Parse failed', pe.message);
                            throw pe;
                        }
                        var rows = jsonToRows(d);
                        if (!rows.length) {
                            throw new Error('JSON contained no user rows — ' + url);
                        }
                        rows.forEach(function (raw) {
                            var u = norm(raw);
                            if (u) appendRow(u.id, u.name, u.email, label);
                        });
                        if (CU_DEBUG) logWarn(label + ' — OK', url);
                        removeLoadingPlaceholder();
                        if (window.__combinedRefresh) window.__combinedRefresh();
                    })
                    .catch(function (err) {
                        var nextErr = err || new Error('Unknown fetch error');
                        if (CU_DEBUG) logWarn('Retry after URL failed: ' + url, nextErr.message || nextErr);
                        tryUrl(i + 1, nextErr);
                    });
            })(0, null);
        });
    })();
    </script>
    <?php endif; ?>

    <?php if (!empty($_GET['debug_sources'])): ?>
    <div class="debug-sources">
        <h2>Remote fetch debug (remove ?debug_sources from URL when done)</h2>
        <pre><?php echo htmlspecialchars(json_encode($remote_fetch_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
    </div>
    <?php endif; ?>

    </div><?php /* .container */ ?>
    </main>

</body>
</html>
