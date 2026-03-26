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
        // Browser fetch needs CORS. The header must match how visitors open THIS page exactly (https vs http, www vs no-www).
        // Friend can use: if (in_array($_SERVER['HTTP_ORIGIN'] ?? '', ['https://mgcodes.com','https://www.mgcodes.com'], true)) { header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']); }
        // Or for public read-only JSON: header('Access-Control-Allow-Origin: *');
        'fetch_in_browser' => true,
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
    $fbPath = resolve_local_json_path($fallbackRel);
    if ($fbPath === '' || !is_readable($fbPath)) {
        return null;
    }
    $fileRaw = @file_get_contents($fbPath);
    if ($fileRaw === false || $fileRaw === '') {
        return null;
    }
    [$decoded, $fe] = decode_json_from_api_body($fileRaw);
    $tryRows        = json_to_user_rows($decoded ?? []);
    if ($fe !== null || count($tryRows) === 0) {
        return null;
    }
    return [
        'rows'  => $tryRows,
        'raw'   => $fileRaw,
        'label' => basename($fbPath) . ' (local fallback_json_file)',
    ];
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

require_once 'db.php';

foreach ($companies as $company) {
    $label = $company['label'] ?? 'Unknown';

    if (!empty($company['local_db'])) {
        // Local database (Company A)
        $sql    = "SELECT id, name, email FROM users";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['source'] = $label;
                $all_users[]   = $row;
            }
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
            'note'       => 'Rows appear after JavaScript runs. Friend’s API must allow CORS from this site.',
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
}

$conn->close();

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combined Users - All Companies</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; max-width: 900px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #4a90d9; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .source { font-size: 0.9em; color: #666; }
        .browser-load-note { font-size: 0.9em; color: #555; max-width: 900px; margin: 12px 0; }
        .browser-fetch-err { color: #b00020; }
        .debug-sources { margin-top: 2rem; max-width: 900px; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 6px; overflow-x: auto; }
        .debug-sources h2 { font-size: 14px; margin: 0 0 8px; color: #fff; }
        .source-warn { max-width: 900px; background: #fff3cd; border: 1px solid #ffc107; color: #664d03; padding: 12px 14px; border-radius: 6px; margin: 16px 0; font-size: 14px; }
        .source-warn ul { margin: 8px 0 0 18px; padding: 0; }
    </style>
</head>
<body>
    <h1>Combined Users</h1>
    <p><?php echo $intro_sentence; ?></p>

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
    <p class="browser-load-note">Some sources load in your browser (not on the server). Your friend’s API must send <code>Access-Control-Allow-Origin</code> for this <strong>exact</strong> address: <strong><?php echo htmlspecialchars($combined_users_page_origin, ENT_QUOTES, 'UTF-8'); ?></strong> (if you use <code>https://mgcodes.com</code> but people open <code>www</code>, those are different for CORS). Send that string to them, or they can allow <code>*</code> for public JSON only. Open DevTools (F12) → Console if rows still don’t appear.</p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Source</th>
            </tr>
        </thead>
        <tbody id="combined-users-tbody">
            <?php if (empty($all_users) && empty($browser_fetch_jobs)): ?>
                <tr>
                    <td colspan="4">No users found.</td>
                </tr>
            <?php else: ?>
                <?php if (empty($all_users) && !empty($browser_fetch_jobs)): ?>
                <tr id="combined-users-placeholder"><td colspan="4">Loading remote users in your browser…</td></tr>
                <?php endif; ?>
                <?php foreach ($all_users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)($user['id'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars((string)($user['name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)($user['email'] ?? '')); ?></td>
                        <td class="source"><?php echo htmlspecialchars((string)($user['source'] ?? 'Unknown')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($browser_fetch_jobs)): ?>
    <script type="application/json" id="combined-users-browser-jobs"><?php
        echo json_encode($browser_fetch_jobs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES);
    ?></script>
    <script>
    (function () {
        var el = document.getElementById('combined-users-browser-jobs');
        if (!el || !el.textContent) return;
        var jobs;
        try { jobs = JSON.parse(el.textContent); } catch (e) { return; }
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
        function appendRow(id, name, email, source) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + esc(id) + '</td><td>' + esc(name) + '</td><td>' + esc(email) + '</td><td class="source">' + esc(source) + '</td>';
            tbody.appendChild(tr);
        }
        function errRow(label, msg) {
            var tr = document.createElement('tr');
            tr.className = 'browser-fetch-err';
            tr.innerHTML = '<td colspan="4">' + esc(label + ': ' + msg) + '</td>';
            tbody.appendChild(tr);
        }

        function removeLoadingPlaceholder() {
            var row = document.getElementById('combined-users-placeholder');
            if (row && row.parentNode) row.parentNode.removeChild(row);
        }

        jobs.forEach(function (job) {
            var label = job.label || 'Unknown';
            var urls = job.urls || [];
            (function tryUrl(i) {
                if (i >= urls.length) {
                    var origin = (typeof location !== 'undefined' && location.origin) ? location.origin : '';
                    errRow(label, 'Could not load (CORS, network, or HTML instead of JSON). The API must include header Access-Control-Allow-Origin: ' + origin + ' or *. Open F12 → Console / Network for details.');
                    removeLoadingPlaceholder();
                    return;
                }
                fetch(urls[i], { method: 'GET', mode: 'cors', credentials: 'omit', cache: 'no-store' })
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.text();
                    })
                    .then(function (text) {
                        var d = parseJsonLoose(text);
                        if (!d) throw new Error('invalid JSON or HTML');
                        var rows = jsonToRows(d);
                        if (!rows.length) throw new Error('No user rows in JSON');
                        rows.forEach(function (raw) {
                            var u = norm(raw);
                            if (u) appendRow(u.id, u.name, u.email, label);
                        });
                        removeLoadingPlaceholder();
                    })
                    .catch(function () { tryUrl(i + 1); });
            })(0);
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
</body>
</html>
