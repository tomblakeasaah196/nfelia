<?php
/**
 * PDFs (dompdf): rendered once per distinct document and kept, so a link
 * opened many times costs one render, and a flood of requests for one
 * document cannot tie the server up.
 *
 *   $file = pdf_file($html, ['font' => 'DejaVu Sans']);   // the kept PDF's path
 *   pdf_send($file, 'Invoice_ADA-INV-001.pdf');            // and stop
 *
 * The key is the rendered HTML itself: any change to the document (a
 * payment, a signature, a new logo) changes the HTML and so the key, with
 * nothing to invalidate by hand. Kept files live under storage/pdf/cache
 * (closed to the web, like all of storage/) and go after 30 days without
 * being opened (pdf_cache_sweep, from cron/planner.php).
 *
 * Remote fetching is off: every picture in a template is a data: URI
 * (logos, seals, QR codes), so dompdf never waits on the network while a
 * visitor waits, and no address inside a document can make this server
 * fetch anything.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/** Bump when dompdf or the options below change, so files made before are not reused. */
const PDF_CACHE_VERSION = 1;

/** A folder under storage/pdf, made if need be. */
function pdf_dir(string $sub = ''): string
{
    $dir = BASE_PATH . '/storage/pdf' . ($sub !== '' ? '/' . trim($sub, '/') : '');
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('could not make ' . $dir);
    }
    return $dir;
}

/**
 * The PDF for this HTML, rendered now or kept from before.
 *   opts: font (default DejaVu Sans), paper (A4), orientation (portrait)
 */
function pdf_file(string $html, array $opts = []): string
{
    $font        = (string)($opts['font'] ?? 'DejaVu Sans');
    $paper       = (string)($opts['paper'] ?? 'A4');
    $orientation = (string)($opts['orientation'] ?? 'portrait');
    $key  = hash('sha256', PDF_CACHE_VERSION . '|' . $font . '|' . $paper . '|' . $orientation . '|' . $html);
    $file = pdf_dir('cache/' . substr($key, 0, 2)) . '/' . $key . '.pdf';
    if (is_file($file) && filesize($file) > 0) {
        @touch($file);   // still wanted: the sweep counts from the last opening
        return $file;
    }

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', $font);
    $options->set('tempDir', pdf_dir('tmp'));
    $options->set('chroot', BASE_PATH);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper($paper, $orientation);
    $dompdf->render();

    // Written beside its final name first, so a half-written file is never sent
    $part = $file . '.' . bin2hex(random_bytes(4)) . '.part';
    if (file_put_contents($part, $dompdf->output()) === false || !@rename($part, $file)) {
        @unlink($part);
        if (!is_file($file)) {
            throw new RuntimeException('could not keep the PDF');
        }
    }
    return $file;
}

/** Send a kept PDF to the browser and stop. */
function pdf_send(string $file, string $filename, bool $attachment = true): never
{
    while (ob_get_level()) { ob_end_clean(); }
    $filename = str_replace(['"', "\r", "\n", '/', '\\'], '', $filename);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('Content-Length: ' . (string)filesize($file));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

/**
 * For PDFs anyone holding a link can ask for: one visitor gets 60 an hour.
 * A kept file costs nothing to send, so an honest reader never meets this;
 * a script pulling the same link in a loop does.
 */
function pdf_public_guard(): void
{
    if (throttle_allows('pdf_ip', client_ip(), 60, 60)) {
        return;
    }
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(429);
    header('Retry-After: 900');
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit("Too many downloads from your connection in the last hour. Please try again in a few minutes.\n");
}

/** Remove kept PDFs nobody has opened for $days days. Returns how many went. */
function pdf_cache_sweep(int $days = 30): int
{
    $gone  = 0;
    $limit = time() - $days * 86400;
    foreach (glob(BASE_PATH . '/storage/pdf/cache/*/*.pdf') ?: [] as $file) {
        if (@filemtime($file) < $limit && @unlink($file)) {
            $gone++;
        }
    }
    foreach (glob(BASE_PATH . '/storage/pdf/cache/*/*.part') ?: [] as $part) {
        if (@filemtime($part) < time() - 3600) {
            @unlink($part);
        }
    }
    return $gone;
}
