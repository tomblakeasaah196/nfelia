<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// The .htaccess "- [F" rules (closed folders), as the live server applies them
foreach (file($_SERVER['DOCUMENT_ROOT'] . '/.htaccess') as $line) {
    if (preg_match('/^RewriteRule\s+(\S+)\s+-\s+\[F([^\]]*)\]/', $line, $m)
        && preg_match('#' . $m[1] . '#' . (stripos($m[2], 'NC') !== false ? 'i' : ''), ltrim($path, '/'))) {
        http_response_code(403);
        return true;
    }
}
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ($path !== '/' && is_file($file)) return false;
$_GET['url'] = ltrim($path, '/');
chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
