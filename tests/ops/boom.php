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
 *   /__boom/picture[/whole]       a PNG cut short (or whole) through save_image()
 */
if (!in_array(APP_ENV, ['local', 'testing'], true)) {
    return;
}
$path = explode('/', trim($_GET['url'] ?? '', '/'));
$what = $path[1] ?? '';

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
    case 'picture':
        // GD warns about a picture it can't read (an upload cut short),
        // the way it warns about one with a wrong colour profile
        $im = imagecreatetruecolor(40, 40);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        $tmp = tempnam(sys_get_temp_dir(), 'boom');
        file_put_contents($tmp, ($path[2] ?? '') === 'whole' ? $png : substr($png, 0, 60));
        $saved = save_image(['tmp_name' => $tmp], $tmp . '.png');
        @unlink($tmp);
        @unlink($tmp . '.png');
        echo $saved ? 'saved' : 'refused';
        exit;
    default:
        http_response_code(404);
        echo 'unknown boom';
        exit;
}
