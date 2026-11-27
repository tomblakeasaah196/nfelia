<?php
/**
 * The deploy's copy into the docroot (bin/deploy_sync.php): only changed
 * files are touched, each replaced in one step (a rename), new folders are
 * made, and the docroot's own folder mode is left as it is. No database:
 *   php tests/deploy/sync_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../bin/deploy_sync.php';

$fails = 0;
function same(string $what, $want, $got): void {
    global $fails;
    if ($want === $got) { echo "  ok  {$what}\n"; return; }
    $fails++;
    echo "FAIL  {$what}\n      want " . var_export($want, true) . "\n      got  " . var_export($got, true) . "\n";
}
function put(string $path, string $text): void { @mkdir(dirname($path), 0755, true); file_put_contents($path, $text); }

$base  = sys_get_temp_dir() . '/nf-sync-' . getmypid();
$stage = "$base/stage";
$root  = "$base/root";
put("$stage/index.php", "<?php echo 'new';");
put("$stage/lib/PHPMailer/src/SMTP.php", "<?php // the same in both");
put("$stage/views/site/new-page.php", "<?php // brand new");
put("$root/index.php", "<?php echo 'old';");
put("$root/lib/PHPMailer/src/SMTP.php", "<?php // the same in both");
put("$root/.env", "SECRET=stays");
chmod($root, 0751);
$same_inode = fileinode("$root/lib/PHPMailer/src/SMTP.php");
$old_inode  = fileinode("$root/index.php");

[$changed, $unchanged, $failed] = deploy_sync($stage, $root);
same('two files changed (one edited, one new), one unchanged, none failed', [2, 1, []], [$changed, $unchanged, $failed]);
same('the edited file has its new content', "<?php echo 'new';", file_get_contents("$root/index.php"));
if (DIRECTORY_SEPARATOR === '/') {
    same('and is a new file, swapped in by a rename, not rewritten in place', true, fileinode("$root/index.php") !== $old_inode);
    same('an unchanged file is not touched at all', $same_inode, fileinode("$root/lib/PHPMailer/src/SMTP.php"));
    same('the docroot keeps its own mode', '751', substr(sprintf('%o', fileperms($root)), -3));
}
same('a new page lands, with its new folder', "<?php // brand new", @file_get_contents("$root/views/site/new-page.php"));
same('what is not in the release is left alone (.env)', 'SECRET=stays', file_get_contents("$root/.env"));
same('no temporary file is left behind', [], array_values(array_filter(
    iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS))),
    fn($f) => str_contains($f->getFilename(), '.deploy-'))));

[$changed] = deploy_sync($stage, $root);
same('a second run finds nothing to change', 0, $changed);

// clean up
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
rmdir($base);

echo $fails ? "\n{$fails} failed\n" : "\nall passed\n";
exit($fails ? 1 : 0);
