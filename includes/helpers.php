<?php
/**
 * General-purpose helpers: escaping, requests, flash messages, CSRF, formatting.
 */

// --- Output ----------------------------------------------------------------

/** HTML-escape a value for output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Path from the current page to a file in the app root ('' from the root, '../' from /admin). */
function root_url(string $path = ''): string
{
    static $prefix = null;
    if ($prefix === null) {
        $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
        $prefix    = ($scriptDir === realpath(__DIR__ . '/..')) ? '' : '../';
    }
    return $prefix . $path;
}

/** True when Apache URL rewriting is active (the root .htaccess sets CLEAN_URLS), so links can drop ".php". */
function clean_urls(): bool
{
    return !empty($_SERVER['CLEAN_URLS']) || !empty($_SERVER['REDIRECT_CLEAN_URLS']);
}

/**
 * Link to a page of this app, written with its file name ('students.php?edit=3', 'index.php#how',
 * '../index.php', 'admin/login.php'). With clean URLs this becomes 'students?edit=3', './#how', '../'
 * or 'admin/login'; without them the file name is returned unchanged.
 */
function page(string $target): string
{
    if (!clean_urls()) {
        return $target;
    }
    $cut    = strcspn($target, '?#');
    $path   = substr($target, 0, $cut);
    $suffix = substr($target, $cut);
    if (preg_match('#(^|/)index\.php$#', $path)) {
        $path = substr($path, 0, -strlen('index.php'));
        $path = $path === '' ? './' : $path;
    } else {
        $path = preg_replace('#\.php$#', '', $path);
    }
    return $path . $suffix;
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $on): string
{
    return $on ? ' checked' : '';
}

/** Inline SVG icon (Lucide-style, 24px grid). */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'check'      => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'zap'        => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
        'list'       => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
        'user-x'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/>',
        'clock'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'file'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'pie'        => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'users'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book'       => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'user'       => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'       => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'plus'       => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'trash'      => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'printer'    => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'search'     => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'alert'      => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'home'       => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'lock'       => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'arrow-left' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'arrow-right'=> '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'chart'      => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'x'          => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'info'       => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'database'   => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'sun'        => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon'       => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** Standalone error page (used before the admin layout is available). Ends the request. */
function render_error_page(string $title, string $message, bool $offerInstall = false, int $status = 500): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $css = root_url('assets/css/style.css');
    echo '<!doctype html><html lang="en"' . theme_attr() . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title><link rel="stylesheet" href="' . e($css) . '">' . theme_script() . '</head>'
        . '<body class="auth-body"><main class="center-card"><div class="card card-pad">'
        . '<div class="error-icon">' . icon('alert') . '</div>'
        . '<h1 class="h2">' . e($title) . '</h1><p class="muted">' . e($message) . '</p>'
        . ($offerInstall ? '<a class="btn btn-primary" href="' . e(page(root_url('install.php'))) . '">' . icon('database') . ' Run the installer</a>' : '')
        . '</div></main></body></html>';
    exit;
}

// --- Theme -----------------------------------------------------------------

/** Attribute for <html>: the configured default theme, used until the visitor picks one. */
function theme_attr(): string
{
    return ' data-theme="' . (DEFAULT_THEME === 'light' ? 'light' : 'dark') . '"';
}

/** Applies the visitor's saved light/dark choice before the page paints, so there is no flash. */
function theme_script(): string
{
    return "<script>try{var t=localStorage.getItem('attendtrack-theme');"
        . "if(t==='light'||t==='dark'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}</script>";
}

/** Sun/moon button that switches between dark and light mode (handled in app.js). */
function theme_toggle(string $class = ''): string
{
    return '<button type="button" class="icon-btn theme-toggle ' . e($class) . '" data-theme-toggle'
        . ' aria-label="Switch between dark and light mode" title="Dark / light mode">'
        . icon('moon', 'icon icon-moon') . icon('sun', 'icon icon-sun') . '</button>';
}

// --- Requests --------------------------------------------------------------

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Trimmed string from POST (preferred) or GET. */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key): int
{
    return (int) input($key, '0');
}

/** Trimmed string from the query string only (for list filters on pages that also take POSTs). */
function query_param(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Single-line text, whitespace collapsed and cut to $max characters. */
function clean_text($value, int $max): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

/** Redirect to a page of this app, given by file name as for page(). */
function redirect(string $url): void
{
    header('Location: ' . page($url));
    exit;
}

/** A "?query" string that is safe to append to a local page name when redirecting back. */
function safe_return(string $query): string
{
    return (strpos($query, '?') === 0 && strpbrk($query, "\r\n") === false) ? $query : '';
}

/** Current query string with some parameters replaced (null or '' removes them). */
function query_with(array $changes): string
{
    $query = array_merge($_GET, $changes);
    $query = array_filter($query, function ($v) {
        return $v !== null && $v !== '';
    });
    return '?' . http_build_query($query);
}

// --- Flash messages & CSRF -------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function render_flashes(): string
{
    $icons = ['success' => 'check', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
    $html  = '';
    foreach (take_flashes() as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '" role="status">'
            . icon($icons[$f['type']] ?? 'info') . '<span>' . e($f['message']) . '</span>'
            . '<button type="button" class="alert-close" data-dismiss aria-label="Dismiss">' . icon('x') . '</button></div>';
    }
    return $html;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Stops the request unless the POST carries this session's CSRF token. */
function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        render_error_page('Form expired', 'Your session expired or the form was resubmitted. Go back, refresh the page and try again.', false, 400);
    }
}

// --- Validation ------------------------------------------------------------

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/** Accepts HH:MM or HH:MM:SS (24-hour). */
function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time);
}

function normalize_time(string $time): string
{
    return strlen($time) === 5 ? $time . ':00' : $time;
}

function today(): string
{
    return date('Y-m-d');
}

// --- Formatting ------------------------------------------------------------

function fmt_date(?string $date): string
{
    return $date ? date('M j, Y', strtotime($date)) : '—';
}

function fmt_day(?string $date): string
{
    return $date ? date('l, F j, Y', strtotime($date)) : '—';
}

function fmt_time(?string $time): string
{
    return $time ? date('g:i A', strtotime($time)) : '—';
}

function fmt_datetime(?string $dt): string
{
    return $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
}

/** Human-readable date range; empty bounds mean "all dates". */
function range_label(string $from, string $to): string
{
    if ($from === '' && $to === '') {
        return 'All dates';
    }
    if ($from === '') {
        return 'Up to ' . fmt_date($to);
    }
    if ($to === '') {
        return 'From ' . fmt_date($from);
    }
    return $from === $to ? fmt_date($from) : fmt_date($from) . ' – ' . fmt_date($to);
}

/** "Last, First" from a row holding first_name and last_name. */
function student_name(array $row): string
{
    return $row['last_name'] . ', ' . $row['first_name'];
}

function initials(string $name): string
{
    $letters = '';
    foreach (preg_split('/\s+/u', trim($name)) as $word) {
        if ($word !== '' && mb_strlen($letters) < 2) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }
    }
    return $letters ?: '?';
}

// --- Pagination ------------------------------------------------------------

function paginate(int $total, int $page, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'total' => $total, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $first = $p['offset'] + 1;
    $last  = min($p['total'], $p['offset'] + $p['limit']);
    $html  = '<nav class="pagination" aria-label="Pages"><span class="muted">Showing ' . $first . '–' . $last . ' of ' . $p['total'] . '</span><div>';
    $html .= $p['page'] > 1
        ? '<a class="btn btn-light btn-sm" href="' . e(query_with(['page' => $p['page'] - 1])) . '">' . icon('arrow-left') . ' Previous</a>'
        : '<span class="btn btn-light btn-sm is-disabled">' . icon('arrow-left') . ' Previous</span>';
    $html .= '<span class="page-of">Page ' . $p['page'] . ' of ' . $p['pages'] . '</span>';
    $html .= $p['page'] < $p['pages']
        ? '<a class="btn btn-light btn-sm" href="' . e(query_with(['page' => $p['page'] + 1])) . '">Next ' . icon('arrow-right') . '</a>'
        : '<span class="btn btn-light btn-sm is-disabled">Next ' . icon('arrow-right') . '</span>';
    return $html . '</div></nav>';
}

// --- CSV export ------------------------------------------------------------

/** Sends rows as a CSV download and ends the request. */
function send_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly
    fputcsv($out, $header);
    foreach ($rows as $row) {
        // Neutralise values Excel would treat as formulas.
        $row = array_map(function ($v) {
            $v = (string) $v;
            return ($v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v;
        }, $row);
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
