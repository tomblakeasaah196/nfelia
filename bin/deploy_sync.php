<?php
/**
 * Copy a staged release into the live docroot, one file at a time and each
 * in one step: only files whose content changed are touched, and each is
 * written beside its old self under a temporary name, then renamed over it.
 * A rename within one folder is atomic, so a request never reads half a
 * file.
 *
 * Why: bin/deploy.sh used to untar the whole repository straight over the
 * docroot, rewriting every file in place on every deploy. On 27 Nov 2026 a
 * request at 00:41:58 met lib/PHPMailer/src/SMTP.php half-written ("Unclosed
 * '{'") and crashed.
 *
 *   php bin/deploy_sync.php STAGE DOCROOT
 *
 * Folders are made when missing, never re-moded (the docroot's own mode
 * keeps the web server able to enter it: see --no-overwrite-dir in
 * deploy.sh's history). Files gone from the repository are left alone, as
 * before: removing one is a deliberate act. Prints one summary line; exits 1
 * if anything could not be written.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** [changed, unchanged, failed paths] for one staged tree copied into a docroot. */
function deploy_sync(string $stage, string $root): array
{
    $stage = rtrim($stage, '/');
    $root  = rtrim($root, '/');
    $changed = 0;
    $same    = 0;
    $failed  = [];
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        $rel    = substr($item->getPathname(), strlen($stage) + 1);
        $target = $root . '/' . $rel;
        if ($item->isDir() && !$item->isLink()) {
            if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                $failed[] = $rel . '/';
            }
            continue;
        }
        $tmp = dirname($target) . '/.deploy-' . getmypid() . '-' . basename($target);
        if ($item->isLink()) {
            $to = (string)readlink($item->getPathname());
            if (is_link($target) && readlink($target) === $to) {
                $same++;
                continue;
            }
            if (@symlink($to, $tmp) && @rename($tmp, $target)) {
                $changed++;
            } else {
                @unlink($tmp);
                $failed[] = $rel;
            }
            continue;
        }
        $from = $item->getPathname();
        if (is_file($target) && !is_link($target) && filesize($target) === filesize($from)
            && hash_file('sha1', $target) === hash_file('sha1', $from)) {
            $same++;
            continue;
        }
        if (@copy($from, $tmp) && @chmod($tmp, fileperms($from) & 0777) && @rename($tmp, $target)) {
            $changed++;
        } else {
            @unlink($tmp);
            $failed[] = $rel;
        }
    }
    return [$changed, $same, $failed];
}

if (realpath(__FILE__) === realpath($argv[0])) {
    if ($argc !== 3) {
        fwrite(STDERR, "usage: php deploy_sync.php STAGE DOCROOT\n");
        exit(2);
    }
    [$stage, $root] = [$argv[1], $argv[2]];
    if (!is_dir($stage) || !is_dir($root)) {
        fwrite(STDERR, "[deploy] sync: STAGE and DOCROOT must both be folders\n");
        exit(2);
    }
    [$changed, $same, $failed] = deploy_sync($stage, $root);
    echo "[deploy] sync: {$changed} file" . ($changed === 1 ? '' : 's') . " changed, {$same} unchanged"
       . ($failed ? ', ' . count($failed) . ' could not be written: ' . implode(', ', array_slice($failed, 0, 10)) : '') . "\n";
    exit($failed ? 1 : 0);
}
