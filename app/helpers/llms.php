<?php
/**
 * Nfelia — what AI assistants read about Nfelia: nfelia.com/llms.txt and
 * nfelia.com/llms-full.txt (the llms.txt convention, llmstxt.org).
 *
 * llms.txt is the short guide: an H1, a one-line summary in a blockquote,
 * the key facts (what Nfelia is, who makes it, who it is for, what a month
 * costs in each country as Ops → Prices has it today, the free trial, no
 * commission, whose records they are, how to reach us), then "## " sections
 * of "- [Title](url): one line" for every page of the site, and "## Optional"
 * for what can be skipped. It is made on each request (the response is
 * cached for an hour).
 *
 * llms-full.txt is every page the sitemaps offer, as plain text (Markdown):
 * each view drawn as the site draws it, without the layout, then turned
 * into headings, lists, tables and [links](url). Each page starts with its
 * title (#), its address and when it changed; its own headings follow one
 * level down (its h1 is ##). It is kept in storage/cache/llms-full.txt and
 * made again after six hours, or at once when a page list or view is newer.
 *
 * Both come from site_pages() (app/helpers/site.php), so a page added there
 * is listed here too. index.php serves them (BEING FOUND), before any
 * session starts.
 */

/** How long llms-full.txt is kept before it is made again, in seconds. */
const LLMS_FULL_TTL = 6 * 3600;

/** Elements whose words are not the page's content (menus, buttons, the closing call...). */
const LLMS_SKIP_TAGS = ['script', 'style', 'svg', 'noscript', 'template', 'button', 'form', 'input', 'select', 'textarea', 'label',
                        'nav', 'dialog', 'img', 'picture', 'source', 'video', 'audio', 'iframe', 'canvas', 'object', 'head', 'link', 'meta'];
const LLMS_SKIP_CLASSES = ['sr-only', 'related', 'cta-band', 'article-aside', 'crumbs', 'hero-actions', 'card-more', 'card-icon', 'skip-link', 'toc', 'wa'];

/* ──────────────────────────────────────────
 *  llms.txt
 * ────────────────────────────────────────── */

function llms_txt(): string {
    $org   = site_org();
    $names = array_column(site_countries(), 'name');
    $where = implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names);

    $out = "# Nfelia\n\n"
         . '> ' . $org['about'] . ' A product of ' . $org['legal'] . ', for small businesses in ' . $where . ".\n\n"
         . 'Nfelia is a web app for small businesses: shops and market traders, Instagram and WhatsApp sellers, fashion designers, '
         . 'salons, caterers, phone and gadget shops, and event planners. Each business gets its own website at yourname.nfelia.com, '
         . 'where customers order, pay and book, plus invoices, quotes and receipts with its logo, debt reminders on WhatsApp, '
         . 'stock that updates itself when it sells, bookings with deposits, order tracking links, delivery notes the customer '
         . 'signs, expenses and a monthly profit report. It opens in the browser on any phone and can be added to the home screen '
         . "like an app, with no app store download.\n\n"
         . 'Nfelia is a product of ' . $org['legal'] . ".\n\n"
         . "What it costs, as of " . date('j F Y') . ":\n\n";
    foreach (site_countries() as $c) {
        $p = site_prices($c['iso']);
        if ($p) {
            $money = $p['money'];
            $out .= '- ' . $c['name'] . ': ' . $money((float)$p['month']) . ' a month, plus ' . $money((float)$p['seat'])
                  . ' a month for each extra person. Free for ' . (int)$p['trial_days'] . " days to start, no card needed.\n";
        } else {
            $out .= '- ' . $c['name'] . ": opening soon, not yet open for sign-ups. Ask on WhatsApp: " . site_wa('Hello Nfelia, I am in ' . $c['name'] . '. When can I start?') . "\n";
        }
    }
    $out .= "\nAlso good to know:\n\n"
          . "- No commission on sales: customers pay the business directly, into its own bank account or mobile money, and the money is the business's. Nfelia charges only the monthly price.\n"
          . "- The records belong to the business. Every record is available as CSV files, free, whatever the plan, even while Nfelia is paused for non-payment; invoices, receipts and the profit report download as PDF while the business is open; "
          . "every month paid for comes with a Records Book (all the records in one Excel workbook that anyone can check is genuine at " . APP_URL . "/verify). "
          . "If a business stops paying, its records are kept for 12 months after its last paid month (or the end of its trial) and can be downloaded until then; the owner is emailed before anything is deleted.\n"
          . "- A paying business's website is open to Google. During the free trial the website works for anyone with the link but is kept off Google.\n"
          . '- Contact: WhatsApp ' . $org['phone_text'] . ' (' . site_wa() . '), phone ' . $org['phone_text'] . ', support ' . $org['support'] . ', sales ' . $org['sales'] . ".\n"
          . ($org['same_as'] ? '- Nfelia\'s own profiles: ' . implode(', ', array_map(fn($label, $url) => $label . ' ' . $url, array_keys($org['same_as']), $org['same_as'])) . ".\n" : '');

    $listed = array_filter(site_pages(), 'seo_site_page_listed');
    $used   = [];
    $take   = function (array $keys_or_pages) use ($listed, &$used): array {
        $pages = [];
        foreach ($keys_or_pages as $p) {
            $p = is_string($p) ? ($listed[$p] ?? null) : $p;
            if ($p && isset($listed[$p['key']]) && !isset($used[$p['key']])) {
                $used[$p['key']] = true;
                $pages[] = $p;
            }
        }
        return $pages;
    };
    $group = fn(string $g) => array_values(array_filter($listed, fn($p) => $p['group'] === $g));
    // Countries in the site's order: Nigeria, Ghana, Kenya, Cameroon
    $by_country = function (array $pages): array {
        $rank = array_flip(array_keys(site_countries()));
        usort($pages, fn($a, $b) => ($rank[$a['country']] ?? 9) <=> ($rank[$b['country']] ?? 9));
        return $pages;
    };

    $sections = [
        'Product'           => $take(array_merge(['home', 'features'], $group('features'))),
        'For your business' => $take(array_merge(['for'], $group('for'))),
        'Pricing'           => $take(array_merge(
            $by_country(array_values(array_filter($listed, fn($p) => $p['alternates'] === 'pricing' || $p['key'] === 'pricing' || str_ends_with($p['key'], '/pricing')))),
            $by_country($group('countries'))
        )),
        'Help centre'       => $take(array_merge(['help'], $group('help'))),
        'Guides'            => $take(array_merge(['blog'], $group('blog'))),
        'Company'           => $take(array_merge(['about', 'contact', 'faq'], $group('legal'))),
    ];
    $sections['More pages'] = $take(array_values($listed));

    foreach ($sections as $title => $pages) {
        if (!$pages) {
            continue;
        }
        $out .= "\n## " . $title . "\n\n";
        foreach ($pages as $page) {
            $out .= llms_link($page, $title === 'Pricing' ? llms_price_note($page) : '');
        }
    }
    $out .= "\n## Optional\n\n"
          . '- [Shops on Nfelia](' . APP_URL . "/shops): the directory of paying businesses that chose to be listed, by city and by what they sell\n"
          . '- [Every page in full](' . APP_URL . "/llms-full.txt): the text of every page above, in one file\n";
    return $out;
}

/** A page as a line of llms.txt: - [Title](url): its summary. */
function llms_link(array $page, string $before = ''): string {
    $note = trim(implode(' ', array_filter([$before, llms_oneline((string)($page['summary'] ?: $page['description']))])));
    return '- [' . str_replace(['[', ']'], ['(', ')'], llms_title($page)) . '](' . site_url($page['key']) . ')' . ($note !== '' ? ': ' . $note : '') . "\n";
}

/** A page's title without the site's name at the end ("Debt tracker app | Nfelia" → "Debt tracker app"). */
function llms_title(array $page): string {
    $title = trim((string)preg_replace('/\s*[|·–—-]\s*Nfelia(\s+[A-Z][a-z]+)?\s*$/u', '', (string)$page['title']));
    return $title !== '' ? $title : (string)$page['crumb'];
}

/** What a month costs in a pricing page's country, to lead its line in llms.txt. */
function llms_price_note(array $page): string {
    if ($page['alternates'] !== 'pricing' && !str_ends_with($page['key'], 'pricing')) {
        return '';
    }
    $country = site_country($page['country']);
    $p = site_prices($country['iso']);
    if (!$p) {
        return $country['name'] . ': not yet open for sign-ups.';
    }
    return $country['name'] . ': ' . ($p['money'])((float)$p['month']) . ' a month, free for ' . (int)$p['trial_days'] . ' days.';
}

function llms_oneline(string $text): string {
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

/* ──────────────────────────────────────────
 *  llms-full.txt
 * ────────────────────────────────────────── */

/** llms-full.txt: from the file kept in storage/cache while it is fresh, else made again and kept. */
function llms_full_txt(): string {
    $file = BASE_PATH . '/storage/cache/llms-full.txt';
    clearstatcache(true, $file);
    if (is_file($file)) {
        $made = (int)filemtime($file);
        if ($made > time() - LLMS_FULL_TTL && $made >= llms_sources_changed()) {
            $text = @file_get_contents($file);
            if (is_string($text) && $text !== '') {
                return $text;
            }
        }
    }
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // One request makes it again; any other that comes meanwhile gets the
    // copy it is replacing, so a stale copy never means every visitor
    // building all the pages at once
    $lock = @fopen($file . '.lock', 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        $stale = is_file($file) ? @file_get_contents($file) : false;
        if (is_string($stale) && $stale !== '') {
            fclose($lock);
            return $stale;
        }
        flock($lock, LOCK_EX);   // no copy at all yet: wait for the one being made
        $text = @file_get_contents($file);
        flock($lock, LOCK_UN);
        fclose($lock);
        if (is_string($text) && $text !== '') {
            return $text;
        }
        $lock = false;
    }
    $text = llms_full_build();
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $text) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        error_log('[Nfelia] llms-full.txt: could not keep a copy in ' . $dir);
    }
    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $text;
}

/** When the newest page list or view (or the landing page) was changed: a copy older than that is made again. */
function llms_sources_changed(): int {
    $newest = 0;
    foreach (array_merge(glob(BASE_PATH . '/app/site/pages/*.php') ?: [],
                         [BASE_PATH . '/views/home/index.php', BASE_PATH . '/app/helpers/site.php', __FILE__]) as $f) {
        $newest = max($newest, (int)@filemtime($f));
    }
    $dir = BASE_PATH . '/views/site/pages';
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $f) {
            $newest = max($newest, (int)$f->getMTime());
        }
    }
    return $newest;
}

/** Every page the sitemaps offer, as text, in the order llms.txt lists them (the site's own order within each group). */
function llms_full_build(): string {
    $org = site_org();
    $out = "# Nfelia: every page of nfelia.com\n\n"
         . '> ' . $org['about'] . ' A product of ' . $org['legal'] . ".\n\n"
         . 'The text of every page of Nfelia\'s website, one after another, for AI assistants. '
         . 'A shorter guide, with a line about each page: ' . APP_URL . '/llms.txt. Made ' . date('j F Y, H:i T') . ".\n";
    $order = ['main' => 0, 'features' => 1, 'for' => 2, 'countries' => 3, 'help' => 4, 'blog' => 5, 'legal' => 6];
    $pages = array_values(array_filter(site_pages(), 'seo_site_page_listed'));
    usort($pages, fn($a, $b) => [$order[$a['group']] ?? 9, $a['order'], $a['crumb']] <=> [$order[$b['group']] ?? 9, $b['order'], $b['crumb']]);
    foreach ($pages as $page) {
        $text = llms_markdown(llms_page_html($page));
        if ($text === '') {
            continue;
        }
        $out .= "\n---\n\n# " . llms_title($page) . "\n\n"
              . 'URL: ' . site_url($page['key']) . "\n"
              . 'Updated: ' . $page['updated'] . "\n\n"
              . $text . "\n";
    }
    return $out;
}

/**
 * The inside of a page's <main>, drawn as the site draws it (site_render)
 * but without the layout. The landing page (no view of its own) is drawn
 * from views/home and its <main> taken out. '' when the view fails.
 */
function llms_page_html(array $page): string {
    try {
        if ($page['view'] === null) {
            $html = llms_draw(BASE_PATH . '/views/home/index.php', $page);
            return preg_match('#<main\b[^>]*>(.*)</main>#is', $html, $m) ? $m[1] : '';
        }
        return llms_draw(BASE_PATH . '/views/site/pages/' . $page['view'] . '.php', $page);
    } catch (Throwable $e) {
        error_log('[Nfelia] llms-full.txt: page ' . $page['key'] . ': ' . $e->getMessage());
        return '';
    }
}

/** A view's output, with $page and $country as site_render gives them. */
function llms_draw(string $file, array $page): string {
    require_once BASE_PATH . '/views/home/_icons.php';
    $GLOBALS['site_page']  = $page;
    $GLOBALS['site_faqs']  = [];
    $GLOBALS['site_nodes'] = [];
    $level = ob_get_level();
    ob_start();
    try {
        (static function (string $__file, array $page): void {
            $country = site_country($page['country']);
            require $__file;
        })($file, $page);
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        throw $e;
    }
    $html = '';
    while (ob_get_level() > $level) {
        $html = ob_get_clean() . $html;
    }
    return $html;
}

/**
 * HTML as plain Markdown: headings as # (one level down: h1 is ##, h2 ###),
 * lists as - (numbered lists keep their numbers), links as [text](url),
 * tables as Markdown tables, FAQ questions in bold; menus, buttons, pictures
 * and the "read next" and closing call blocks left out; whitespace collapsed.
 */
function llms_markdown(string $html): string {
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    $was = libxml_use_internal_errors(true);
    $doc->loadHTML('<html><body>' . mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') . '</body></html>', LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($was);
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }
    $md = llms_md_children($body, 0);
    $md = str_replace("\u{00A0}", ' ', $md);
    $md = (string)preg_replace('/^[ \t]+(?![-*] |\d+\. )/m', '', $md);      // no stray indent, but nested lists keep theirs
    $md = (string)preg_replace('/(?<=\S)[ \t]{2,}/', ' ', $md);
    $md = (string)preg_replace('/[ \t]+$/m', '', $md);
    $md = (string)preg_replace("/\n{3,}/", "\n\n", $md);
    return trim($md);
}

/**
 * The children's text, one after another. Two elements side by side with no
 * space between them (<strong>Paid to you</strong><span>No commission</span>,
 * laid out as separate lines by the CSS) get a space, so words never run
 * together.
 */
function llms_md_children(DOMNode $node, int $depth): string {
    return llms_md_nodes(iterator_to_array($node->childNodes), $depth);
}

function llms_md_nodes(array $nodes, int $depth): string {
    $out = '';
    $prev_element = false;
    foreach ($nodes as $child) {
        $md = llms_md($child, $depth);
        if ($md === '') {
            continue;
        }
        $is_element = $child instanceof DOMElement;
        $touching = $prev_element && $is_element && preg_match('/[\p{L}\p{N}*.:!?”)]$/u', $out) && preg_match('/^[\p{L}\p{N}*\[“(₦]/u', $md);
        $after_bold = str_ends_with($out, '**') && preg_match('/^[\p{L}\p{N}]/u', $md);   // <strong>Before</strong>Regular...
        if ($touching || $after_bold) {
            $out .= ' ';
        }
        $out .= $md;
        $prev_element = $is_element;
    }
    return $out;
}

function llms_md(DOMNode $node, int $depth): string {
    if ($node instanceof DOMText) {
        return (string)preg_replace('/\s+/u', ' ', $node->nodeValue);
    }
    if (!$node instanceof DOMElement) {
        return '';
    }
    $tag = strtolower($node->tagName);
    if (in_array($tag, LLMS_SKIP_TAGS, true) || $node->getAttribute('aria-hidden') === 'true' || $node->hasAttribute('hidden')
        || array_intersect(preg_split('/\s+/', $node->getAttribute('class')) ?: [], LLMS_SKIP_CLASSES)) {
        return '';
    }
    switch ($tag) {
        case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
            $text = llms_oneline(llms_md_children($node, $depth));
            return $text === '' ? '' : "\n\n" . str_repeat('#', min(6, (int)$tag[1] + 1)) . ' ' . $text . "\n\n";
        case 'br':
            return "\n";
        case 'hr':
            return "\n\n";
        case 'strong': case 'b':
            return llms_wrap(llms_md_children($node, $depth), '**');
        case 'em':
            return llms_wrap(llms_md_children($node, $depth), '*');
        case 'a':
            return llms_md_link($node, $depth);
        case 'ul': case 'ol':
            return llms_md_list($node, $depth);
        case 'table':
            return llms_md_table($node, $depth);
        case 'summary': case 'dt':
            $text = llms_oneline(llms_md_children($node, $depth));
            return $text === '' ? '' : "\n\n**" . $text . "**\n\n";
        case 'blockquote':
            $text = trim(llms_md_children($node, $depth));
            return $text === '' ? '' : "\n\n" . preg_replace('/^/m', '> ', $text) . "\n\n";
        case 'p': case 'div': case 'section': case 'article': case 'header': case 'footer': case 'main': case 'aside':
        case 'figure': case 'figcaption': case 'details': case 'dd': case 'dl': case 'address': case 'li':
            return "\n\n" . llms_md_children($node, $depth) . "\n\n";
        default:
            return llms_md_children($node, $depth);
    }
}

/** Bold or italic words, keeping the spaces around them outside the marks. */
function llms_wrap(string $text, string $mark): string {
    $inner = trim($text);
    if ($inner === '') {
        return $text;
    }
    return (preg_match('/^\s/', $text) ? ' ' : '') . $mark . $inner . $mark . (preg_match('/\s$/', $text) ? ' ' : '');
}

/**
 * A link as [text](url). A card (a link holding a heading and words) is
 * [heading](url): words. Links within the page (#...) are just their words.
 */
function llms_md_link(DOMElement $a, int $depth): string {
    $href  = trim($a->getAttribute('href'));
    $label = '';
    $rest  = [];
    foreach ($a->childNodes as $child) {
        if ($child instanceof DOMElement && preg_match('/^h[1-6]$/i', $child->tagName) && $label === '') {
            $label = llms_flat(llms_md($child, $depth));
        } else {
            $rest[] = $child;
        }
    }
    $rest = llms_flat(llms_md_nodes($rest, $depth));
    if ($label === '') {
        [$label, $rest] = [$rest, ''];
    }
    if ($label === '') {
        return '';
    }
    $label = str_replace(['[', ']'], ['(', ')'], $label);
    if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
        return $label . ($rest !== '' ? ': ' . $rest : '');
    }
    return '[' . $label . '](' . str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $href) . ')' . ($rest !== '' ? ': ' . $rest : '');
}

/** Lists: - or 1. for each item, its words on one line, lists inside it indented. */
function llms_md_list(DOMElement $list, int $depth): string {
    $ordered = strtolower($list->tagName) === 'ol';
    $n   = max(1, (int)($list->getAttribute('start') ?: 1));
    $out = $depth === 0 ? "\n\n" : "\n";
    foreach ($list->childNodes as $li) {
        if (!$li instanceof DOMElement || strtolower($li->tagName) !== 'li'
            || array_intersect(preg_split('/\s+/', $li->getAttribute('class')) ?: [], LLMS_SKIP_CLASSES)) {
            continue;
        }
        $words = [];
        $inner = '';
        foreach ($li->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                $inner .= llms_md_list($child, $depth + 1);
            } else {
                $words[] = $child;
            }
        }
        $text = llms_flat(llms_md_nodes($words, $depth));
        if ($text === '' && trim($inner) === '') {
            continue;
        }
        $out .= str_repeat('  ', $depth) . ($ordered ? ($n++) . '. ' : '- ') . $text . "\n" . ltrim($inner, "\n");
    }
    return $out . ($depth === 0 ? "\n" : '');
}

/**
 * Block text on one line, for a list item or a table cell: a heading
 * inside it becomes bold words ("**Add your products** Take a photo...").
 */
function llms_flat(string $md): string {
    $parts = array_values(array_filter(array_map('trim', preg_split("/\n{2,}/", trim($md)) ?: []), fn($s) => $s !== ''));
    foreach ($parts as $i => $part) {
        if (preg_match('/^#+\s+(.*)$/s', $part, $m)) {
            $parts[$i] = '**' . trim($m[1]) . '**';
        }
    }
    return llms_oneline(implode(' ', $parts));
}

/** A table as a Markdown table: the first row is its head. */
function llms_md_table(DOMElement $table, int $depth): string {
    $rows = [];
    $width = 0;
    foreach ($table->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $cell) {
            if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                $cells[] = str_replace('|', '\|', llms_flat(llms_md_children($cell, $depth)));
            }
        }
        if ($cells) {
            $rows[] = $cells;
            $width = max($width, count($cells));
        }
    }
    if (!$rows) {
        return '';
    }
    $caption = $table->getElementsByTagName('caption')->item(0);
    $out = "\n\n" . ($caption ? '**' . llms_oneline($caption->textContent) . "**\n\n" : '');
    foreach ($rows as $i => $cells) {
        $out .= '| ' . implode(' | ', array_pad($cells, $width, '')) . " |\n";
        if ($i === 0) {
            $out .= '|' . str_repeat(' --- |', $width) . "\n";
        }
    }
    return $out . "\n";
}
