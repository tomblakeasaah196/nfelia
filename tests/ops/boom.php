<?php
/**
 * Things going wrong on purpose, inside the real app (index.php loads this
 * on a test copy only, for /__boom/...), so tests/ops/monitor_test.php can
 * see each one recorded exactly as a real one would be.
 *
 *   /__boom/exception[/anything]  an exception: the 500 page with its code
 *   /__boom/warning               a warning, and the page carries on
 *   /__boom/fatal                 out of memory: a fatal error
 *   /__boom/slow                  a page that takes 3.2 seconds
 *   /__boom/rollback              an exception inside a transaction that is rolled back
 *   /__boom/signed-in             an exception for whoever is signed in
 *   /__boom/again                 the same exception as /exception, from the same line
 */
if (!in_array(APP_ENV, ['local', 'testing'], true)) {
    return;
}
$what = explode('/', trim($_GET['url'] ?? '', '/'))[1] ?? '';

function boom_throw(): never
{
    throw new RuntimeException('Boom: the test broke this on purpose for ada@example.test, 08031234567');
}

switch ($what) {
    case 'exception':
    case 'again':
        boom_throw();
    case 'warning':
        $none = [];
        $x = $none['missing_key'];   // Warning: Undefined array key
        echo 'carried on';
        exit;
    case 'fatal':
        ini_set('memory_limit', '16M');
        $big = str_repeat('x', 64 * 1024 * 1024);
        echo strlen($big);
        exit;
    case 'slow':
        usleep(3200000);
        echo 'slow';
        exit;
    case 'rollback':
        $db = get_db();
        $db->beginTransaction();
        $db->exec("UPDATE businesses SET name = name WHERE id = 2");
        throw new LogicException('Boom inside a transaction');
    case 'signed-in':
        session_start();
        throw new DomainException('Boom for a signed-in person');
    default:
        http_response_code(404);
        echo 'unknown boom';
        exit;
}
