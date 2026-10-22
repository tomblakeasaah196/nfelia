<?php
/**
 * Writing a Records Book: the .xlsx itself (app/helpers/records.php decides
 * who gets one and when; app/helpers/records_sheets.php says what is in it).
 *
 *   Cover          the Nfelia logo at the top, the business and its story
 *                  with Nfelia, its figures, the QR code that checks the
 *                  book, and its own logo at the foot
 *   Contents       every sheet, linked, with how many rows it holds
 *   At a glance    money in and out by month (with a chart), top customers,
 *                  best sellers, who owes
 *   one sheet per kind of record, each an Excel table: sort and filter
 *                  from the header, a line under the title that adds up
 *                  what the filters show, a live link to each document and
 *                  its verify code
 *   About          how to use it, check it and take it elsewhere
 *
 * In the business's own colours (Nfelia's, when it never chose any), and
 * styled cell by cell, not with Excel's table styles, so it looks the same
 * in Excel, Google Sheets, LibreOffice and phone apps. Text is always
 * written as text: a customer's note that starts with "=" stays a note.
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\{Axis, Chart, DataSeries, DataSeriesValues, Legend, PlotArea, Title};
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Conditional, Fill};
use PhpOffice\PhpSpreadsheet\Worksheet\{Drawing, PageSetup, Table, Worksheet};
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const RB_FONT  = 'Calibri';
const RB_INK   = '1F0A14';
const RB_MUTED = '5B4650';
const RB_LINE  = 'E8E4DA';
const RB_IN    = '00764A';   // money in
const RB_OWED  = 'B42318';   // money owed
const RB_FIRST_ROW = 5;      // data starts under the title, its sum line, the way back and the header

/** Statuses coloured in their sheet: good news green, money owed red. */
const RB_GOOD = ['Paid', 'Accepted', 'Invoiced', 'Done', 'Confirmed', 'Cleared', 'Delivered', 'Received', 'Signed Off', 'Published', 'Approved'];
const RB_BAD  = ['Overdue', 'Not paid', 'Declined', 'Turned down', 'Failed', 'Cancelled', 'Open'];

final class RecordsBook
{
    private Spreadsheet $s;
    private array $brand;
    private string $symbol;
    private array $counts = [];
    private array $ledger = [];
    private array $temp = [];
    private array $made = [];      // sheet key => worksheet title
    private array $empty = [];     // chosen, but nothing in the period

    /**
     * $book: the records_books row (code, sheets, date_from, date_to, created_at)
     * $business: the businesses row, with owner_name, country, currency_code
     * $facts: records_facts() for the period
     */
    public function __construct(private PDO $db, private array $book, private array $business, private array $facts)
    {
        $this->brand  = records_brand($business);
        $this->symbol = trim((string)($business['currency_symbol'] ?? '')) ?: '₦';
    }

    /** Write the book to $path. Returns [counts per sheet, ledger of verifiable records]. */
    public function write(string $path): array
    {
        $this->s = new Spreadsheet();
        $this->setup();

        $cover    = $this->s->getActiveSheet()->setTitle('Cover');
        $contents = $this->s->createSheet()->setTitle('Contents');
        $glance   = $this->s->createSheet()->setTitle('At a glance');

        $sheets = records_sheets();
        foreach (records_book_sheet_keys(json_decode((string)$this->book['sheets'], true) ?: []) as $key) {
            $this->data_sheet($key, $sheets[$key]);
        }
        $about = $this->s->createSheet()->setTitle('About this book');

        $this->cover($cover);
        $this->contents($contents);
        $this->glance($glance);
        $this->about($about);

        $this->s->setActiveSheetIndex(0);
        $writer = new Xlsx($this->s);
        $writer->setIncludeCharts(true);
        $writer->setPreCalculateFormulas(true);   // the figures are there before Excel opens it (phones, previews)
        $writer->save($path);

        foreach ($this->temp as $file) @unlink($file);
        $this->s->disconnectWorksheets();
        return ['counts' => $this->counts, 'ledger' => $this->ledger];
    }

    /**
     * One record and its lines, as a one-sheet workbook for someone the
     * business agreed to share it with ($request: the record_requests row),
     * with its money trail (records_extract_trail()): an invoice's payments
     * and receipts; an order's invoice, payments and delivery notes. False
     * when the record no longer exists.
     */
    public function extract(string $key, int $id, array $request, string $path): bool
    {
        $sheets = records_sheets();
        $sheet = $sheets[$key];
        [$sql, $args] = records_sheet_sql_one($sheet, (int)$this->business['id'], $id);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $row = $stmt->fetch();
        if (!$row) return false;

        $this->s = new Spreadsheet();
        $this->setup();
        $p = $this->brand;
        $ws = $this->s->getActiveSheet()->setTitle('Record');
        $ws->setShowGridlines(false);
        $ws->getColumnDimension('A')->setWidth(3);
        foreach (range('B', 'I') as $c) $ws->getColumnDimension($c)->setWidth(13);
        $ws->getColumnDimension('J')->setWidth(3);
        $ws->getTabColor()->setRGB($p['primary']);

        $ws->getRowDimension(2)->setRowHeight(36);
        $this->image($ws, BASE_PATH . '/assets/img/records/nfelia-lockup.png', 'B2', 40, 0, 4);
        $this->text($ws, 'F2', 'RECORD EXTRACT', ['size' => 9, 'bold' => true, 'color' => $p['secondary_text'], 'merge' => 'F2:I2', 'align' => 'right']);
        $this->text($ws, 'F3', records_when(date('Y-m-d H:i:s')), ['size' => 9, 'color' => RB_MUTED, 'merge' => 'F3:I3', 'align' => 'right']);
        $this->thread($ws, 'B4', 760);
        $ws->getRowDimension(6)->setRowHeight(34);
        $this->text($ws, 'B6', (string)$this->business['name'], ['size' => 22, 'bold' => true, 'color' => $p['text'], 'merge' => 'B6:I6', 'font' => $p['heading_font']]);
        $number = (string)($row['number'] ?? $row['invoice'] ?? '');
        $this->text($ws, 'B7', trim($sheet['label'] . ' ' . $number), ['size' => 13, 'bold' => true, 'merge' => 'B7:I7']);

        // Every column of the record, one per line
        $r = 9;
        foreach ($sheet['cols'] as [$head, $field, $type]) {
            if ($type === 'formula_money' || ($row[$field] ?? null) === null || $row[$field] === '') continue;
            $this->text($ws, 'B' . $r, $head, ['color' => RB_MUTED, 'merge' => 'B' . $r . ':C' . $r]);
            $ws->mergeCells('D' . $r . ':I' . $r);
            $this->put($ws, 4, $r, $type, $row[$field]);
            $this->format_cell($ws, 'D' . $r, $type);
            $ws->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText($type === 'long');
            if ($type === 'long' && mb_strlen((string)$row[$field]) > 70) $ws->getRowDimension($r)->setRowHeight(15 * (int)ceil(mb_strlen((string)$row[$field]) / 70) + 4);
            $ws->getStyle('B' . $r . ':I' . $r)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(RB_LINE);
            $r++;
        }
        $r--;   // the last row written: each part below leaves one row empty before it

        // Its lines
        if (!empty($sheet['lines'])) {
            $lines = $sheets[$sheet['lines']];
            [$sql, $args] = records_sheet_sql_one($lines, (int)$this->business['id'], $id);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($args);
            $cols = array_values(array_filter($lines['cols'], fn($c) => !in_array($c[1], ['number', 'issued', 'placed', 'made', 'spent_on', 'paid_on'], true)));
            $this->extract_table($ws, $r, $lines['title'], array_slice($cols, 0, 8), $stmt->fetchAll());
        }

        // How it was paid, and for an order where its goods went
        foreach (records_extract_trail_rows($this->db, (int)$this->business['id'], $key, $id) as [$title, $cols, $rows]) {
            $this->extract_table($ws, $r, $title, $cols, $rows);
        }

        // Who it is for, who agreed, and its code
        $r += 2;
        $top = $r;
        $code = records_row_code((string)$this->book['code'], (string)$sheet['verify'], $id);
        $for = $request['organisation'] ? $request['name'] . ' (' . $request['organisation'] . ')' : $request['name'];
        foreach ([
            'Made by Nfelia on ' . records_when(date('Y-m-d H:i:s')) . ' for ' . $for . ', with ' . $this->business['name'] . '\'s agreement on ' . records_when($request['decided_at'] ?? null, false) . '.',
            'It shows the record as it is today. It was also in ' . $this->business['name'] . '\'s Records Book of ' . records_when($this->book['created_at'], false) . ': open nfelia.com/verify and type the code to compare.',
        ] as $line) {
            $this->text($ws, 'B' . $r, $line, ['merge' => 'B' . $r . ':F' . $r, 'wrap' => true]);
            $ws->getRowDimension($r)->setRowHeight(44);
            $r++;
        }
        $this->text($ws, 'B' . $r, $code, ['size' => 13, 'bold' => true, 'color' => $p['text'], 'merge' => 'B' . $r . ':F' . $r, 'font' => 'Consolas', 'link' => records_verify_url($code)]);
        $this->image($ws, $this->temp_file('png', qr_png(records_verify_url($code), 5)), 'G' . $top, 120, 30, 0);
        $r += 3;
        $this->text($ws, 'B' . $r, 'Powered by Nfelia', ['size' => 9, 'bold' => true, 'color' => $p['text'], 'merge' => 'B' . $r . ':C' . $r, 'link' => APP_URL]);
        $ws->getStyle('B' . $r . ':I' . $r)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(RB_LINE);
        $ws->setSelectedCells('A1');
        $this->print_setup($ws, 'Record extract', 110, null, true);

        $this->s->getProperties()->setTitle($this->business['name'] . ' · ' . trim($sheet['label'] . ' ' . $number))
            ->setSubject('Record extract')->setCustomProperty('Nfelia verify code', $code);
        $writer = new Xlsx($this->s);
        $writer->setPreCalculateFormulas(true);
        $writer->save($path);
        foreach ($this->temp as $file) @unlink($file);
        $this->s->disconnectWorksheets();
        return true;
    }

    /** A small table on the extract under $title, from row $r on ($r moves to its last row); nothing when there are no rows. */
    private function extract_table(Worksheet $ws, int &$r, string $title, array $cols, array $rows): void
    {
        if (!$rows) return;
        $p = $this->brand;
        $last = Coordinate::stringFromColumnIndex(1 + count($cols));
        $r += 2;
        $this->text($ws, 'B' . $r, $title, ['size' => 12, 'bold' => true, 'merge' => 'B' . $r . ':I' . $r]);
        $r++;
        foreach ($cols as $i => [$head, , $type]) {
            $cell = Coordinate::stringFromColumnIndex(2 + $i) . $r;
            $this->text($ws, $cell, $head, ['bold' => true, 'color' => $p['on_primary'], 'align' => in_array($type, ['money', 'qty', 'int'], true) ? 'right' : 'left']);
        }
        $ws->getStyle('B' . $r . ':' . $last . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['primary']);
        foreach ($rows as $row) {
            $r++;
            foreach ($cols as $i => [, $field, $type]) {
                $this->put($ws, 2 + $i, $r, $type, $row[$field] ?? null);
                $this->format_cell($ws, Coordinate::stringFromColumnIndex(2 + $i) . $r, $type);
            }
            $ws->getStyle('B' . $r . ':' . $last . $r)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(RB_LINE);
        }
    }

    /** Number formats for one cell, by its type (the extract's values). */
    private function format_cell(Worksheet $ws, string $cell, string $type): void
    {
        $f = match ($type) {
            'money' => $this->money_format(false),
            'qty' => '#,##0.##', 'int' => '#,##0',
            'date' => 'd mmm yyyy', 'datetime' => 'd mmm yyyy, h:mm AM/PM', 'time' => 'h:mm AM/PM',
            default => null,
        };
        if ($f) $ws->getStyle($cell)->getNumberFormat()->setFormatCode($f);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  THE WORKBOOK
     * ═══════════════════════════════════════════════════════════════ */

    private function setup(): void
    {
        $b = $this->business;
        $this->s->getProperties()
            ->setCreator('Nfelia')
            ->setLastModifiedBy('Nfelia')
            ->setTitle($b['name'] . ' · Records Book')
            ->setSubject('Records of ' . $b['name'] . ', ' . records_period_text($this->book))
            ->setDescription('Made by Nfelia on ' . records_when($this->book['created_at']) . '. Check it at ' . records_verify_url($this->book['code']) . '.')
            ->setKeywords('Nfelia Records Book ' . $this->book['code'])
            ->setCategory('Records Book')
            ->setCompany((string)$b['name'])
            ->setCustomProperty('Nfelia verify code', (string)$this->book['code']);
        // Charts take their colours from the theme
        $theme = $this->s->getTheme();
        $theme->setThemeColor('accent1', $this->brand['primary']);
        $theme->setThemeColor('accent2', $this->brand['secondary']);
        $theme->setThemeColor('accent3', RB_IN);
        $theme->setThemeColor('accent4', RB_OWED);
        $this->s->getDefaultStyle()->getFont()->setName(RB_FONT)->setSize(10.5)->getColor()->setRGB(RB_INK);
        $this->s->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  ONE SHEET OF RECORDS
     * ═══════════════════════════════════════════════════════════════ */

    private function data_sheet(string $key, array $sheet): void
    {
        [$sql, $args] = records_sheet_sql($sheet, (int)$this->business['id'], $this->book['date_from'] ?: null, $this->book['date_to'] ?: null);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);

        $cols = $sheet['cols'];
        $verify = $sheet['verify'] ?? null;
        $link   = $sheet['link'] ?? null;
        if ($link) $cols[] = ['Open', '__link', 'link', 11];
        if ($verify) $cols[] = ['Verify', '__verify', 'verify', 17];
        $n = count($cols);
        $last_col = Coordinate::stringFromColumnIndex($n);
        $by_key = [];
        foreach ($cols as $i => $c) $by_key[$c[1]] = Coordinate::stringFromColumnIndex($i + 1);

        $ws = null;
        $row = RB_FIRST_ROW - 1;
        $cents = [];
        while ($r = $stmt->fetch()) {
            if ($ws === null) {
                $ws = $this->s->createSheet()->setTitle($sheet['title']);
            }
            $row++;
            foreach ($cols as $i => [$head, $field, $type]) {
                $col = $i + 1;
                if ($type === 'link') {
                    $url = $link($r);
                    if ($url) $this->link_formula($ws, $col, $row, $url, 'Open ↗');
                    continue;
                }
                if ($type === 'verify') {
                    $code = records_row_code((string)$this->book['code'], $verify, (int)$r['id']);
                    $this->link_formula($ws, $col, $row, records_verify_url($code), $code);
                    continue;
                }
                if ($type === 'formula_money') {
                    $f = preg_replace_callback('/\{(\w+)\}/', fn($m) => ($by_key[$m[1]] ?? 'A') . $row, $sheet['formulas'][$field]);
                    $ws->setCellValueExplicit([$col, $row], '=' . $f, DataType::TYPE_FORMULA);
                    continue;
                }
                $value = $r[$field] ?? null;
                if ($type === 'money' && $value !== null && fmod((float)$value, 1.0) != 0.0) $cents[$col] = true;
                $this->put($ws, $col, $row, $type, $value);
            }
            if ($verify && isset($sheet['facts'])) {
                $facts = ($sheet['facts'])($r);
                $this->ledger[$verify . (int)$r['id']] = ['f' => $facts, 'h' => records_facts_hash($facts)];
            }
        }
        if ($ws === null) {
            $this->empty[$key] = true;
            $this->counts[$key] = 0;
            return;
        }
        $this->made[$key] = $sheet['title'];
        $this->counts[$key] = $row - RB_FIRST_ROW + 1;
        $last = $row;
        $p = $this->brand;

        // Title, the line that adds up what is shown, the way back
        $ws->setCellValueExplicit('A1', $sheet['title'], DataType::TYPE_STRING);
        $ws->getStyle('A1')->getFont()->setSize(18)->setBold(true)->getColor()->setRGB($p['text']);
        $ws->getRowDimension(1)->setRowHeight(30);
        $right = Coordinate::stringFromColumnIndex(max(2, $n - 2));
        $ws->setCellValueExplicit($right . '1', $this->business['name'] . ' · Records Book · ' . records_when($this->book['created_at'], false), DataType::TYPE_STRING);
        $ws->getStyle($right . '1')->getFont()->setSize(9)->getColor()->setRGB(RB_MUTED);

        $ws->setCellValueExplicit('A2', '=' . $this->sum_line($sheet, $cols, $by_key), DataType::TYPE_FORMULA);
        $ws->getStyle('A2')->getFont()->setSize(11)->setBold(true)->getColor()->setRGB(RB_INK);
        $ws->getRowDimension(2)->setRowHeight(20);

        $this->sheet_link($ws, 'A3', 'Contents', '← Contents');
        $ws->setCellValueExplicit('B3', records_period_text($this->book) . '. Tap a column\'s arrow to sort or filter; the line above adds up what you see.', DataType::TYPE_STRING);
        $ws->getStyle('B3')->getFont()->setSize(9)->getColor()->setRGB(RB_MUTED);

        // The header
        foreach ($cols as $i => [$head, , , $width]) {
            $ws->setCellValueExplicit([$i + 1, 4], $head, DataType::TYPE_STRING);
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $ws->getRowDimension(4)->setRowHeight(30);
        $ws->getStyle('A4:' . $last_col . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $p['on_primary']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $p['primary']]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Columns, by kind; numbers on the right, everything with a little room.
        // A style object applies to whatever the sheet has selected when it is
        // changed, so each is taken just before it is used: otherwise the link
        // colour lands on the header as well, burgundy on burgundy.
        foreach ($cols as $i => [$head, $field, $type]) {
            $c = Coordinate::stringFromColumnIndex($i + 1);
            $range = $c . RB_FIRST_ROW . ':' . $c . $last;
            $right = in_array($type, ['money', 'formula_money', 'qty', 'int'], true);
            $side = $right ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT;
            $ws->getStyle($c . '4:' . $c . $last)->getAlignment()->setHorizontal($side)->setIndent(1);
            $style = $ws->getStyle($range);
            switch ($type) {
                case 'money':
                case 'formula_money':
                    $style->getNumberFormat()->setFormatCode($this->money_format(!empty($cents[$i + 1]), true));
                    break;
                case 'qty':
                    $style->getNumberFormat()->setFormatCode('#,##0.##;-#,##0.##;"–"');
                    break;
                case 'int':
                    $style->getNumberFormat()->setFormatCode('#,##0');
                    break;
                case 'date':
                    $style->getNumberFormat()->setFormatCode('d mmm yyyy');
                    break;
                case 'datetime':
                    $style->getNumberFormat()->setFormatCode('d mmm yyyy, h:mm AM/PM');
                    break;
                case 'time':
                    $style->getNumberFormat()->setFormatCode('h:mm AM/PM');
                    break;
                case 'long':
                    $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                    break;
                case 'stars':
                    $style->getFont()->getColor()->setRGB($p['secondary_text']);
                    break;
                case 'link':
                case 'verify':
                    $style->getFont()->setUnderline(true)->getColor()->setRGB($p['text']);
                    break;
            }
            if (str_ends_with($type, '_status') || $type === 'status') {
                $this->status_colours($ws, $range);
            }
        }

        $ws->getStyle('A4:' . $last_col . '4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // An Excel table: filters in the header, and the name the sum line uses
        $table = new Table('A4:' . $last_col . $last, $sheet['table']);
        $table->setStyle((new TableStyle())->setTheme(TableStyle::TABLE_STYLE_NONE)->setShowRowStripes(false));
        $ws->addTable($table);

        // Bands, so a long row is easy to follow across
        $band = new Conditional();
        $band->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)=0');
        $band->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['tint']);
        $band->getStyle()->getFill()->getEndColor()->setRGB($p['tint']);
        $bands = $ws->getStyle('A' . RB_FIRST_ROW . ':' . $last_col . $last)->getConditionalStyles();
        $ws->getStyle('A' . RB_FIRST_ROW . ':' . $last_col . $last)->setConditionalStyles(array_merge([$band], $bands));
        $ws->getStyle('A' . RB_FIRST_ROW . ':' . $last_col . $last)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(RB_LINE);

        $ws->freezePane('B' . RB_FIRST_ROW);
        $ws->setSelectedCells('A' . RB_FIRST_ROW);
        $ws->getTabColor()->setRGB($p['primary']);
        $this->print_setup($ws, $sheet['title'], array_sum(array_column($cols, 3)), 4);
    }

    /** One value, as the type it is: text stays text, dates are real dates, money is a number. */
    private function put(Worksheet $ws, int $col, int $row, string $type, mixed $value): void
    {
        if ($value === null || $value === '') return;
        switch ($type) {
            case 'money':
            case 'qty':
            case 'int':
                $ws->setCellValueExplicit([$col, $row], (float)$value, DataType::TYPE_NUMERIC);
                return;
            case 'date':
            case 'datetime':
                $serial = records_excel_date((string)$value, $type === 'date');
                if ($serial !== null) $ws->setCellValueExplicit([$col, $row], $serial, DataType::TYPE_NUMERIC);
                return;
            case 'time':
                [$h, $m] = array_map('intval', explode(':', (string)$value) + [0, 0]);
                $ws->setCellValueExplicit([$col, $row], ($h * 60 + $m) / 1440, DataType::TYPE_NUMERIC);
                return;
            case 'stars':
                $n = max(0, min(5, (int)$value));
                if ($n) $ws->setCellValueExplicit([$col, $row], str_repeat('★', $n) . str_repeat('☆', 5 - $n), DataType::TYPE_STRING);
                return;
            case 'text':
            case 'long':
            case 'phone':
            case 'status':
                $ws->setCellValueExplicit([$col, $row], records_clean_text((string)$value), DataType::TYPE_STRING);
                return;
            default:   // a stored value, in Nfelia's words
                $words = records_status($type, $value);
                if ($words !== '') $ws->setCellValueExplicit([$col, $row], $words, DataType::TYPE_STRING);
        }
    }

    /**
     * The line under a sheet's title, as a formula over its table, so it
     * follows the filters: "128 invoices · ₦4,200,000 in total · …".
     */
    private function sum_line(array $sheet, array $cols, array $by_key): string
    {
        $table = $sheet['table'];
        $first = $cols[0][0];
        [$one, $many] = $sheet['noun'];
        $count = 'SUBTOTAL(103,' . $table . '[' . records_table_header($first) . '])';
        $parts = [$count . '&IF(' . $count . '=1," ' . $one . '"," ' . $many . '")'];
        foreach ($sheet['sum'] ?? [] as $field) {
            foreach ($cols as [$head, $f]) {
                if ($f !== $field) continue;
                $parts[] = '" · ' . strtolower($head) . ' "&TEXT(SUBTOTAL(109,' . $table . '[' . records_table_header($head) . ']),"' . str_replace('"', '""', $this->money_format(false)) . '")';
            }
        }
        return implode('&', $parts);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  THE COVER
     * ═══════════════════════════════════════════════════════════════ */

    private function cover(Worksheet $ws): void
    {
        $b = $this->business;
        $p = $this->brand;
        $f = $this->facts;
        $code = (string)$this->book['code'];
        $ws->setShowGridlines(false);
        $ws->getColumnDimension('A')->setWidth(3);
        foreach (range('B', 'I') as $c) $ws->getColumnDimension($c)->setWidth(13);
        $ws->getColumnDimension('J')->setWidth(3);
        $ws->getTabColor()->setRGB($p['primary']);

        // Nfelia at the top, the thread under it
        $ws->getRowDimension(2)->setRowHeight(36);
        $this->image($ws, BASE_PATH . '/assets/img/records/nfelia-lockup.png', 'B2', 40, 0, 4);
        $this->text($ws, 'F2', 'RECORDS BOOK', ['size' => 9, 'bold' => true, 'color' => $p['secondary_text'], 'merge' => 'F2:I2', 'align' => 'right']);
        $this->text($ws, 'F3', records_when($this->book['created_at']), ['size' => 9, 'color' => RB_MUTED, 'merge' => 'F3:I3', 'align' => 'right']);
        $this->thread($ws, 'B4', 760);

        // The business
        $ws->getRowDimension(6)->setRowHeight(40);
        $this->text($ws, 'B6', (string)$b['name'], ['size' => 26, 'bold' => true, 'color' => $p['text'], 'merge' => 'B6:I6', 'font' => $p['heading_font']]);
        $line = trim((string)($b['tagline'] ?? ''));
        if ($line !== '') $this->text($ws, 'B7', $line, ['size' => 12, 'italic' => true, 'color' => RB_MUTED, 'merge' => 'B7:I7']);
        $this->text($ws, 'B8', records_since_text((string)$b['created_at'], (string)$this->book['created_at']),
                    ['size' => 10.5, 'bold' => true, 'color' => $p['text'], 'merge' => 'B8:I8']);
        $ws->getStyle('B8:I8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['tint']);
        $ws->getStyle('B8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
        $ws->getRowDimension(8)->setRowHeight(24);

        // In its own words (the About on its shop), then the facts
        $r = 10;
        $story = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($b['about_text'] ?? ''))));
        if ($story !== '') {
            $story = mb_strlen($story) > 420 ? rtrim(mb_substr($story, 0, 417)) . '…' : $story;
            $this->text($ws, 'B10', $story, ['merge' => 'B10:I10', 'wrap' => true, 'color' => RB_INK]);
            $ws->getRowDimension(10)->setRowHeight(16 * (int)ceil(mb_strlen($story) / 95) + 8);
            $r = 12;
        }
        $this->text($ws, 'B' . $r, 'About the business', ['size' => 12, 'bold' => true, 'merge' => 'B' . $r . ':I' . $r]);
        $rows = array_filter([
            'Owner'        => $b['owner_name'] ?? '',
            'What it does' => records_category_text($b),
            'Address'      => trim((string)($b['address'] ?? '')),
            'Phone'        => $b['phone'] ?? '',
            'WhatsApp'     => !empty($b['whatsapp']) ? [$b['whatsapp'], 'https://wa.me/' . preg_replace('/\D/', '', (string)$b['whatsapp'])] : '',
            'Email'        => !empty($b['email']) ? [$b['email'], 'mailto:' . $b['email']] : '',
            'Website'      => !empty($b['shop_url']) ? [preg_replace('#^https?://#', '', $b['shop_url']), $b['shop_url']] : '',
            'Instagram'    => !empty($b['social_instagram']) ? [$b['social_instagram'], records_social_url('instagram', (string)$b['social_instagram'])] : '',
            'Facebook'     => !empty($b['social_facebook']) ? [$b['social_facebook'], records_social_url('facebook', (string)$b['social_facebook'])] : '',
            'TikTok'       => !empty($b['social_tiktok']) ? [$b['social_tiktok'], records_social_url('tiktok', (string)$b['social_tiktok'])] : '',
            'Registered'   => $b['registration_number'] ?? '',
            'Country'      => trim(($b['country'] ?? '') . (!empty($b['currency_code']) ? ' · amounts in ' . $b['currency_code'] : '')),
        ], fn($v) => $v !== '' && $v !== null);
        $r++;
        foreach ($rows as $label => $value) {
            $this->text($ws, 'B' . $r, $label, ['color' => RB_MUTED, 'merge' => 'B' . $r . ':C' . $r]);
            if (is_array($value)) {
                $this->text($ws, 'D' . $r, (string)$value[0], ['merge' => 'D' . $r . ':I' . $r, 'link' => $value[1]]);
            } else {
                $this->text($ws, 'D' . $r, (string)$value, ['merge' => 'D' . $r . ':I' . $r, 'wrap' => $label === 'Address']);
                if ($label === 'Address' && mb_strlen((string)$value) > 60) $ws->getRowDimension($r)->setRowHeight(30);
            }
            $r++;
        }

        // Its figures, in four tiles
        $r++;
        $this->text($ws, 'B' . $r, 'In this book', ['size' => 12, 'bold' => true, 'merge' => 'B' . $r . ':E' . $r]);
        $this->text($ws, 'F' . $r, records_period_text($this->book), ['size' => 9, 'color' => RB_MUTED, 'merge' => 'F' . $r . ':I' . $r, 'align' => 'right']);
        $r++;
        $orders = (int)$f['orders'] > 0;
        $this->tile($ws, 'B', 'C', $r, number_format((int)$f['customers']), 'Customers');
        $this->tile($ws, 'D', 'E', $r, $this->money_text($f['invoiced']), 'Sales invoiced');
        $this->tile($ws, 'F', 'G', $r, $this->money_text($f['received']), 'Money received', RB_IN);
        $this->tile($ws, 'H', 'I', $r, $orders ? number_format((int)$f['orders']) : $this->money_text($f['spent']), $orders ? 'Orders' : 'Spent');
        $r += 3;

        // This book: when, what, and the QR code that checks it
        $top = $r;
        $this->text($ws, 'B' . $r, 'Check this book', ['size' => 12, 'bold' => true, 'merge' => 'B' . $r . ':F' . $r]);
        $r++;
        $sheets = count($this->made);
        $rows_total = array_sum($this->counts);
        foreach ([
            'Made by Nfelia on ' . records_when($this->book['created_at']) . '.',
            records_period_text($this->book) . ': ' . $sheets . ' sheet' . ($sheets === 1 ? '' : 's') . ', ' . number_format($rows_total) . ' record' . ($rows_total === 1 ? '' : 's') . '.',
            'Scan the code, or open nfelia.com/verify and type it, to see that this book is genuine and unchanged.',
        ] as $line) {
            $this->text($ws, 'B' . $r, $line, ['merge' => 'B' . $r . ':F' . $r, 'wrap' => true]);
            $ws->getRowDimension($r)->setRowHeight(mb_strlen($line) > 64 ? 30 : 18);
            $r++;
        }
        $this->text($ws, 'B' . $r, records_code_display($code), ['size' => 16, 'bold' => true, 'color' => $p['text'], 'merge' => 'B' . $r . ':D' . $r,
                                                                'font' => 'Consolas', 'link' => records_verify_url($code)]);
        $this->text($ws, 'E' . $r, 'nfelia.com/verify', ['color' => RB_MUTED, 'merge' => 'E' . $r . ':F' . $r, 'link' => records_verify_url($code)]);
        $ws->getRowDimension($r)->setRowHeight(26);
        $this->image($ws, $this->temp_file('png', qr_png(records_verify_url($code), 6)), 'G' . $top, 136, 40, 0);

        // The business's own logo at the foot, and Nfelia's line
        $r += 3;
        if ($logo = records_logo_file($b)) {
            $this->temp[] = $logo;
            $ws->getRowDimension($r)->setRowHeight(56);
            $this->image($ws, $logo, 'B' . $r, 64, 0, 4);
            $r += 2;
        }
        $ws->getStyle('B' . $r . ':I' . $r)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(RB_LINE);
        $ws->getRowDimension($r)->setRowHeight(22);
        $this->text($ws, 'B' . $r, 'Powered by Nfelia', ['size' => 9, 'bold' => true, 'color' => $p['text'], 'merge' => 'B' . $r . ':C' . $r, 'link' => APP_URL]);
        $this->text($ws, 'D' . $r, 'Your records, kept safe and always yours.', ['size' => 9, 'color' => RB_MUTED, 'merge' => 'D' . $r . ':G' . $r]);
        $this->text($ws, 'H' . $r, 'Open your Nfelia ↗', ['size' => 9, 'color' => $p['text'], 'merge' => 'H' . $r . ':I' . $r, 'align' => 'right', 'link' => APP_URL . '/login']);

        $ws->setSelectedCells('A1');
        $this->print_setup($ws, 'Cover', 110, null, true);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  CONTENTS
     * ═══════════════════════════════════════════════════════════════ */

    private function contents(Worksheet $ws): void
    {
        $p = $this->brand;
        $ws->setShowGridlines(false);
        $ws->getColumnDimension('A')->setWidth(3);
        $ws->getColumnDimension('B')->setWidth(24);
        $ws->getColumnDimension('C')->setWidth(64);
        $ws->getColumnDimension('D')->setWidth(12);
        $ws->getTabColor()->setRGB($p['secondary']);
        $this->text($ws, 'B2', 'What\'s in this book', ['size' => 18, 'bold' => true, 'color' => $p['text'], 'merge' => 'B2:D2']);
        $ws->getRowDimension(2)->setRowHeight(30);
        $this->text($ws, 'B3', $this->business['name'] . ' · ' . records_period_text($this->book), ['color' => RB_MUTED, 'merge' => 'B3:D3']);

        $r = 5;
        $this->sheet_link($ws, 'B' . $r, 'At a glance', 'At a glance');
        $this->text($ws, 'C' . $r, 'Money in and out by month, your best customers and sellers, and who owes you.', []);
        $r++;
        $sheets = records_sheets();
        foreach (RECORDS_GROUPS as $group => $label) {
            $keys = array_values(array_filter(array_keys($sheets), fn($k) => $sheets[$k]['group'] === $group && isset($this->made[$k])));
            if (!$keys) continue;
            $r++;
            $this->text($ws, 'B' . $r, $label, ['bold' => true, 'color' => $p['on_primary'], 'merge' => 'B' . $r . ':D' . $r]);
            $ws->getStyle('B' . $r . ':D' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['primary']);
            $ws->getRowDimension($r)->setRowHeight(22);
            $r++;
            foreach ($keys as $key) {
                $sheet = $sheets[$key];
                $this->sheet_link($ws, 'B' . $r, $sheet['title'], (isset($sheet['line_of']) ? '   ' : '') . $sheet['title']);
                $this->text($ws, 'C' . $r, $sheet['about'], []);
                $ws->setCellValueExplicit('D' . $r, (int)$this->counts[$key], DataType::TYPE_NUMERIC);
                $ws->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0');
                $ws->getStyle('B' . $r . ':D' . $r)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(RB_LINE);
                $r++;
            }
        }
        $r++;
        $this->sheet_link($ws, 'B' . $r, 'About this book', 'About this book');
        $this->text($ws, 'C' . $r, 'How to use, check and move these records.', []);
        if ($this->empty) {
            $r += 2;
            $none = array_map(fn($k) => $sheets[$k]['title'], array_keys($this->empty));
            $when = empty($this->book['date_from']) && empty($this->book['date_to']) ? 'nothing in them yet' : 'nothing in them in this period';
            $this->text($ws, 'B' . $r, 'Left out, with ' . $when . ': ' . implode(', ', $none) . '.', ['merge' => 'B' . $r . ':D' . $r, 'wrap' => true, 'color' => RB_MUTED, 'size' => 9.5]);
            $ws->getRowDimension($r)->setRowHeight(14 * (int)ceil(mb_strlen(implode(', ', $none)) / 100 + 1) + 6);
        }
        $r += 2;
        $this->text($ws, 'B' . $r, 'Each sheet is an Excel table. Tap the arrow on a column to sort or filter it. Every sheet has a link back here at the top.',
                    ['merge' => 'B' . $r . ':D' . $r, 'wrap' => true, 'color' => RB_MUTED, 'size' => 9.5]);
        $ws->getRowDimension($r)->setRowHeight(28);
        $this->print_setup($ws, 'Contents', 100, null, true);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  AT A GLANCE
     * ═══════════════════════════════════════════════════════════════ */

    private function glance(Worksheet $ws): void
    {
        $p = $this->brand;
        $f = $this->facts;
        $ws->setShowGridlines(false);
        $ws->getColumnDimension('A')->setWidth(3);
        foreach (range('B', 'I') as $c) $ws->getColumnDimension($c)->setWidth(15);
        $ws->getColumnDimension('J')->setWidth(3);
        $ws->getTabColor()->setRGB($p['secondary']);
        $this->text($ws, 'B2', 'At a glance', ['size' => 18, 'bold' => true, 'color' => $p['text'], 'merge' => 'B2:F2']);
        $this->sheet_link($ws, 'H2', 'Contents', '← Contents');
        $ws->getRowDimension(2)->setRowHeight(30);
        $this->text($ws, 'B3', records_period_text($this->book) . '. Money in is invoice payments and sales paid on the spot, as Nfelia\'s profit and loss counts it: money kept for a customer\'s next order counts once, when it came in.',
                    ['color' => RB_MUTED, 'merge' => 'B3:I3', 'size' => 9.5, 'wrap' => true]);
        $ws->getRowDimension(3)->setRowHeight(27);

        $this->tile($ws, 'B', 'C', 5, $this->money_text($f['invoiced']), 'Sales invoiced');
        $this->tile($ws, 'D', 'E', 5, $this->money_text($f['received']), 'Money in', RB_IN);
        $this->tile($ws, 'F', 'G', 5, $this->money_text($f['spent']), 'Spent');
        $this->tile($ws, 'H', 'I', 5, $this->money_text($f['owed_to_you']), 'Owed to you now', RB_OWED);

        // Month by month, then the same as a chart
        $r = 9;
        $this->text($ws, 'B' . $r, 'Month by month', ['size' => 12, 'bold' => true, 'merge' => 'B' . $r . ':F' . $r]);
        $head = ++$r;
        foreach (['B' => 'Month', 'C' => 'Invoiced', 'D' => 'Money in', 'E' => 'Spent', 'F' => 'In less spent'] as $c => $h) {
            $this->text($ws, $c . $r, $h, ['bold' => true, 'color' => $p['on_primary'], 'align' => $c === 'B' ? 'left' : 'right']);
        }
        $ws->getStyle('B' . $r . ':F' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['primary']);
        $months = $f['months'] ?? [];
        foreach ($months as $m) {
            $r++;
            $ws->setCellValueExplicit('B' . $r, $m['label'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit('C' . $r, (float)$m['invoiced'], DataType::TYPE_NUMERIC);
            $ws->setCellValueExplicit('D' . $r, (float)$m['received'], DataType::TYPE_NUMERIC);
            $ws->setCellValueExplicit('E' . $r, (float)$m['spent'], DataType::TYPE_NUMERIC);
            $ws->setCellValueExplicit('F' . $r, '=D' . $r . '-E' . $r, DataType::TYPE_FORMULA);
            if (($r - $head) % 2 === 0) $ws->getStyle('B' . $r . ':F' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['tint']);
        }
        if (!$months) {
            $r++;
            $this->text($ws, 'B' . $r, 'Nothing was invoiced, received or spent in this period.', ['color' => RB_MUTED, 'merge' => 'B' . $r . ':F' . $r]);
        } else {
            $r++;
            $ws->setCellValueExplicit('B' . $r, 'Total', DataType::TYPE_STRING);
            foreach (['C', 'D', 'E', 'F'] as $c) {
                $ws->setCellValueExplicit($c . $r, '=SUM(' . $c . ($head + 1) . ':' . $c . ($r - 1) . ')', DataType::TYPE_FORMULA);
            }
            $ws->getStyle('B' . $r . ':F' . $r)->getFont()->setBold(true);
            $ws->getStyle('B' . $r . ':F' . $r)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($p['primary']);
            $ws->getStyle('C' . ($head + 1) . ':F' . $r)->getNumberFormat()->setFormatCode($this->money_format(false));
            $neg = new Conditional();
            $neg->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_LESSTHAN)->addCondition('0');
            $neg->getStyle()->getFont()->getColor()->setRGB(RB_OWED);
            $ws->getStyle('F' . ($head + 1) . ':F' . $r)->setConditionalStyles([$neg]);
            $any = array_filter($months, fn($m) => $m['invoiced'] > 0 || $m['received'] > 0 || $m['spent'] > 0);
            if (count($months) >= 2 && $any) {
                $this->money_chart($ws, $head, $head + count($months), $r + 2);
                $r += 18;
            }
        }

        // Best customers, best sellers, who owes
        $r += 2;
        $r = $this->glance_list($ws, $r, 'Best customers', ['Customer', 'Invoiced', 'Still owes'], $f['top_customers'] ?? [], ['money', 'money']);
        $r++;
        $r = $this->glance_list($ws, $r, 'Best sellers', ['Item', 'Sold', 'Came to'], $f['top_items'] ?? [], ['qty', 'money']);
        $r++;
        $this->glance_list($ws, $r, 'Who owes you most', ['Customer', 'Owes', 'Oldest unpaid'], $f['owing'] ?? [], ['money', 'text']);
        $ws->setSelectedCells('A1');
        $this->print_setup($ws, 'At a glance', 120, null, true);
    }

    /** A small table on At a glance. Returns the next free row. */
    private function glance_list(Worksheet $ws, int $r, string $title, array $heads, array $rows, array $types): int
    {
        $p = $this->brand;
        $this->text($ws, 'B' . $r, $title, ['size' => 12, 'bold' => true, 'merge' => 'B' . $r . ':F' . $r]);
        $r++;
        $this->text($ws, 'B' . $r, $heads[0], ['bold' => true, 'color' => $p['on_primary'], 'merge' => 'B' . $r . ':D' . $r]);
        $this->text($ws, 'E' . $r, $heads[1], ['bold' => true, 'color' => $p['on_primary'], 'align' => 'right']);
        $this->text($ws, 'F' . $r, $heads[2], ['bold' => true, 'color' => $p['on_primary'], 'align' => 'right']);
        $ws->getStyle('B' . $r . ':F' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['primary']);
        if (!$rows) {
            $r++;
            $this->text($ws, 'B' . $r, 'None in this period.', ['color' => RB_MUTED, 'merge' => 'B' . $r . ':F' . $r]);
            return $r + 1;
        }
        foreach ($rows as $row) {
            $r++;
            $this->text($ws, 'B' . $r, (string)$row[0], ['merge' => 'B' . $r . ':D' . $r]);
            foreach ([1 => 'E', 2 => 'F'] as $i => $c) {
                $type = $types[$i - 1];
                if ($type === 'text') {
                    $this->text($ws, $c . $r, (string)$row[$i], ['align' => 'right']);
                } else {
                    $ws->setCellValueExplicit($c . $r, (float)$row[$i], DataType::TYPE_NUMERIC);
                    $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($type === 'money' ? $this->money_format(false) : '#,##0.##');
                }
            }
            $ws->getStyle('B' . $r . ':F' . $r)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(RB_LINE);
        }
        return $r + 1;
    }

    /** Invoiced, money in and spent, month by month, as columns. */
    private function money_chart(Worksheet $ws, int $head, int $last, int $at): void
    {
        $sheet = "'At a glance'";
        $months = $last - $head;
        $cats = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheet . '!$B$' . ($head + 1) . ':$B$' . $last, null, $months)];
        $labels = $values = [];
        foreach (['C' => $this->brand['secondary'], 'D' => RB_IN, 'E' => RB_OWED] as $col => $color) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheet . '!$' . $col . '$' . $head, null, 1);
            $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheet . '!$' . $col . '$' . ($head + 1) . ':$' . $col . '$' . $last, null, $months);
            $v->setFillColor($color);
            $values[] = $v;
        }
        $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0, 1, 2], $labels, $cats, $values);
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        $money = new Axis();
        $money->setAxisNumberProperties($this->money_format(false), true);
        $chart = new Chart('money', new Title('Invoiced, money in and spent'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]),
                           yAxis: $money);
        $chart->setTopLeftPosition('B' . $at);
        $chart->setBottomRightPosition('J' . ($at + 16));
        $ws->addChart($chart);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  ABOUT
     * ═══════════════════════════════════════════════════════════════ */

    private function about(Worksheet $ws): void
    {
        $p = $this->brand;
        $b = $this->business;
        $ws->setShowGridlines(false);
        $ws->getColumnDimension('A')->setWidth(3);
        $ws->getColumnDimension('B')->setWidth(100);
        $ws->getTabColor()->setRGB($p['secondary']);
        $this->text($ws, 'B2', 'About this book', ['size' => 18, 'bold' => true, 'color' => $p['text']]);
        $ws->getRowDimension(2)->setRowHeight(30);
        $this->sheet_link($ws, 'B3', 'Contents', '← Contents');

        $money = ($b['currency_code'] ?? '') !== '' ? $this->symbol . ' (' . $b['currency_code'] . ')' : $this->symbol;
        $blocks = [
            'What it is' => [
                'This book holds ' . $b['name'] . '\'s records from Nfelia, exactly as they were on ' . records_when($this->book['created_at']) . '. ' . records_period_text($this->book) . '.',
                'Amounts are in ' . $money . '. Dates and times are Lagos time. Records deleted in Nfelia are not in it.',
            ],
            'Using it' => [
                'Every sheet is an Excel table. Tap the arrow at the top of a column to sort it, or to show only some rows. The line under each title adds up what you can see.',
                'You can keep working in it: add rows at the bottom of a table and they join it. "Open ↗" opens the document on Nfelia, as your customer saw it.',
            ],
            'Checking it' => [
                'Anyone you share this book with, a bank, a landlord, an accountant, can check it is genuine: scan the QR code on the cover, or open nfelia.com/verify and type its code, ' . records_code_display((string)$this->book['code']) . '.',
                'Each invoice, receipt, payment and other record has its own code in its Verify column, to check that one record.',
                'Nfelia can only vouch for the file as we made it. If it is changed, or opened and saved again, nfelia.com/verify says so. Keep this copy as it is, and share copies of it.',
            ],
            'Moving your records' => [
                'Each document and its lines share a number (Invoices and Invoice lines share the invoice number, for example), so accounting systems such as Odoo, QuickBooks or Sage can import them: save a sheet as CSV and import it.',
                'Prefer plain files? Your free plain copy (CSV files) is in Nfelia, under More → Your records.',
            ],
            'Questions' => [
                'Email support@nfelia.com, or WhatsApp +234 816 978 9106. Your Nfelia is at ' . preg_replace('#^https?://#', '', APP_URL) . '.',
            ],
        ];
        $r = 5;
        foreach ($blocks as $head => $paras) {
            $this->text($ws, 'B' . $r, $head, ['size' => 12, 'bold' => true, 'color' => $p['text']]);
            $ws->getRowDimension($r)->setRowHeight(22);
            $r++;
            foreach ($paras as $para) {
                $this->text($ws, 'B' . $r, $para, ['wrap' => true]);
                $ws->getRowDimension($r)->setRowHeight(15 * max(1, (int)ceil(mb_strlen($para) / 105)) + 4);
                $r++;
            }
            $r++;
        }
        $this->text($ws, 'B' . $r, 'Made with Nfelia · nfelia.com', ['size' => 9, 'bold' => true, 'color' => $p['text'], 'link' => APP_URL]);
        $this->print_setup($ws, 'About this book', 100, null, true);
    }

    /* ═══════════════════════════════════════════════════════════════
     *  SMALL PARTS
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Text in a cell. $o: size, bold, italic, color, font, merge (a range),
     * align (left, right, center), wrap, link (a URL).
     */
    private function text(Worksheet $ws, string $cell, string $value, array $o = []): void
    {
        $ws->setCellValueExplicit($cell, records_clean_text($value), DataType::TYPE_STRING);
        if (!empty($o['merge'])) $ws->mergeCells($o['merge']);
        if (!empty($o['link'])) {
            $ws->getCell($cell)->getHyperlink()->setUrl($o['link']);
            if (empty($o['color'])) $o['color'] = $this->brand['text'];
        }
        $font = $ws->getStyle($cell)->getFont();   // used before anything else is selected
        if (isset($o['size'])) $font->setSize($o['size']);
        if (!empty($o['bold'])) $font->setBold(true);
        if (!empty($o['italic'])) $font->setItalic(true);
        if (!empty($o['font'])) $font->setName($o['font']);
        if (!empty($o['color'])) $font->getColor()->setRGB($o['color']);
        if (!empty($o['link'])) $font->setUnderline(true);
        $align = $ws->getStyle($o['merge'] ?? $cell)->getAlignment();
        if (!empty($o['align'])) $align->setHorizontal(['left' => Alignment::HORIZONTAL_LEFT, 'right' => Alignment::HORIZONTAL_RIGHT, 'center' => Alignment::HORIZONTAL_CENTER][$o['align']]);
        if (!empty($o['wrap'])) $align->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    /** A link to another sheet of the book. */
    private function sheet_link(Worksheet $ws, string $cell, string $title, string $label): void
    {
        $ws->setCellValueExplicit($cell, $label, DataType::TYPE_STRING);
        $ws->getCell($cell)->getHyperlink()->setUrl("sheet://'" . str_replace("'", "''", $title) . "'!A1");
        $ws->getStyle($cell)->getFont()->setUnderline(true)->setBold(true)->getColor()->setRGB($this->brand['text']);
    }

    /** A clickable link written as a HYPERLINK formula (no relationship per row, so big sheets stay light). */
    private function link_formula(Worksheet $ws, int $col, int $row, string $url, string $label): void
    {
        $q = fn(string $s) => '"' . str_replace('"', '""', $s) . '"';
        $ws->setCellValueExplicit([$col, $row], '=HYPERLINK(' . $q($url) . ',' . $q($label) . ')', DataType::TYPE_FORMULA);
    }

    /** A figure with its label, in a coloured tile spanning $c1:$c2 over three rows from $r. */
    private function tile(Worksheet $ws, string $c1, string $c2, int $r, string $value, string $label, ?string $color = null): void
    {
        $p = $this->brand;
        $this->text($ws, $c1 . $r, $label, ['size' => 9, 'color' => RB_MUTED, 'merge' => $c1 . $r . ':' . $c2 . $r]);
        $this->text($ws, $c1 . ($r + 1), $value, ['size' => 17, 'bold' => true, 'color' => $color ?? $p['text'], 'merge' => $c1 . ($r + 1) . ':' . $c2 . ($r + 1)]);
        $ws->getRowDimension($r + 1)->setRowHeight(28);
        $box = $ws->getStyle($c1 . $r . ':' . $c2 . ($r + 1));
        $box->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($p['tint']);
        $box->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB($color ?? $p['primary']);
        $ws->getStyle($c1 . $r . ':' . $c2 . ($r + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
    }

    /** A picture, $height px high, at $cell. */
    private function image(Worksheet $ws, string $file, string $cell, int $height, int $x = 0, int $y = 0): void
    {
        if (!is_file($file)) return;
        $d = new Drawing();
        $d->setPath($file);
        $d->setResizeProportional(true);
        $d->setHeight($height);
        $d->setCoordinates($cell);
        $d->setOffsetX($x);
        $d->setOffsetY($y);
        $d->setWorksheet($ws);
    }

    /** The Nfelia thread across the cover: a burgundy line, stitched in gold. */
    private function thread(Worksheet $ws, string $cell, int $width): void
    {
        $w = $width * 2;
        $h = 16;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagealphablending($img, true);
        imagefilledrectangle($img, 0, 6, $w, 9, imagecolorallocate($img, 0x8B, 0x00, 0x4A));
        $gold = imagecolorallocate($img, 0xB6, 0x9F, 0x5E);
        for ($x = 10; $x < $w - 10; $x += 22) {
            imagefilledrectangle($img, $x, 7, $x + 6, 8, $gold);
        }
        ob_start();
        imagepng($img);
        $this->image($ws, $this->temp_file('png', (string)ob_get_clean()), $cell, 8, 0, 6);
    }

    /** A file under the system's temp folder, deleted once the book is written. */
    private function temp_file(string $ext, string $bytes): string
    {
        $file = tempnam(sys_get_temp_dir(), 'nfrb') . '.' . $ext;
        file_put_contents($file, $bytes);
        return $this->temp[] = $file;
    }

    /** Statuses that are good news in green, money owed or refused in red. */
    private function status_colours(Worksheet $ws, string $range): void
    {
        $rules = [];
        foreach ([[RB_GOOD, RB_IN], [RB_BAD, RB_OWED]] as [$words, $color]) {
            foreach ($words as $word) {
                $c = new Conditional();
                $c->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"' . $word . '"');
                $c->getStyle()->getFont()->setBold(true)->getColor()->setRGB($color);
                $rules[] = $c;
            }
        }
        $ws->getStyle($range)->setConditionalStyles($rules);
    }

    private function print_setup(Worksheet $ws, string $title, int $width_chars, ?int $repeat_row, bool $portrait = false): void
    {
        $setup = $ws->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setOrientation(!$portrait && $width_chars > 110 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(0);
        if ($repeat_row) $setup->setRowsToRepeatAtTopByStartAndEnd($repeat_row, $repeat_row);
        $ws->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $name = str_replace('&', '&&', (string)$this->business['name']);
        $ws->getHeaderFooter()->setOddHeader('&L&8' . $name . ' · ' . str_replace('&', '&&', $title) . '&R&8Page &P of &N');
        $ws->getHeaderFooter()->setOddFooter('&L&8Records Book ' . records_code_display((string)$this->book['code']) . ' · check it at nfelia.com/verify&R&8Powered by Nfelia');
    }

    /** Excel's number format for this business's money: ₦1,250 or 15,000 FCFA; nothing shows as a dash in the sheets. */
    private function money_format(bool $cents, bool $dash = false): string
    {
        $n = $cents ? '#,##0.00' : '#,##0';
        $sym = str_replace('"', '', $this->symbol);
        if (preg_match('/^\p{L}{2,}$/u', $sym)) {
            return $n . '" ' . $sym . '";-' . $n . '" ' . $sym . '";' . ($dash ? '"–"' : '0" ' . $sym . '"');
        }
        return '"' . $sym . '"' . $n . ';-"' . $sym . '"' . $n . ';' . ($dash ? '"–"' : '"' . $sym . '"0');
    }

    private function money_text(mixed $amount): string
    {
        $sym = $this->symbol;
        $n = number_format((float)$amount, fmod((float)$amount, 1.0) != 0.0 ? 2 : 0);
        return preg_match('/^\p{L}{2,}$/u', $sym) ? $n . ' ' . $sym : $sym . $n;
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  SHARED BY THE WRITER, THE PLAIN COPY AND THE VERIFY PAGE
 * ═══════════════════════════════════════════════════════════════════ */

/** The sheets to write, in catalogue order, each followed by its lines. */
function records_book_sheet_keys(array $chosen): array
{
    $sheets = records_sheets();
    $want = array_flip(array_filter($chosen, fn($k) => isset($sheets[$k]) && !isset($sheets[$k]['line_of'])));
    $out = [];
    foreach ($sheets as $key => $sheet) {
        if (isset($sheet['line_of'])) continue;
        if (!isset($want[$key])) continue;
        $out[] = $key;
        if (!empty($sheet['lines'])) $out[] = $sheet['lines'];
    }
    return $out;
}

/**
 * The business's colours for its book: its own when it chose them, Nfelia's
 * when it never did. Text on a colour is white or ink, whichever reads; the
 * colour used for words on white is darkened until it reads too.
 */
function records_brand(array $business): array
{
    $hex = fn($c) => strtoupper(ltrim((string)$c, '#'));
    $primary = $hex($business['primary_color'] ?? '');
    $secondary = $hex($business['secondary_color'] ?? '');
    $defaults = ['1A1A2E', 'C27A62', 'C5A55A', ''];
    if (!preg_match('/^[0-9A-F]{6}$/', $primary) || in_array($primary, $defaults, true)) {
        [$primary, $secondary] = ['8B004A', 'B69F5E'];
    }
    if (!preg_match('/^[0-9A-F]{6}$/', $secondary)) {
        $secondary = 'B69F5E';
    }
    $fonts = ['corporate_serif' => 'Cambria', 'elegant_serif' => 'Georgia', 'classic_tech' => 'Consolas'];
    return [
        'primary'        => $primary,
        'secondary'      => $secondary,
        'on_primary'     => records_contrast($primary, 'FFFFFF') >= 4.5 ? 'FFFFFF' : RB_INK,
        'text'           => records_readable($primary),
        'secondary_text' => records_readable($secondary),
        'tint'           => records_mix($primary, 'FFFFFF', 0.07),
        'heading_font'   => $fonts[$business['font_pairing'] ?? ''] ?? RB_FONT,
    ];
}

/** $hex darkened until it reads on white (contrast 4.5 or more). */
function records_readable(string $hex): string
{
    for ($i = 0; $i < 20 && records_contrast($hex, 'FFFFFF') < 4.5; $i++) {
        $hex = records_mix($hex, '000000', 0.85);
    }
    return $hex;
}

/** $a with $share of it kept and the rest $b: records_mix(c, 'FFFFFF', 0.07) is a pale tint of c. */
function records_mix(string $a, string $b, float $share): string
{
    $x = sscanf($a, '%02x%02x%02x');
    $y = sscanf($b, '%02x%02x%02x');
    $out = '';
    for ($i = 0; $i < 3; $i++) {
        $out .= sprintf('%02X', (int)round($x[$i] * $share + $y[$i] * (1 - $share)));
    }
    return $out;
}

/** WCAG contrast ratio between two colours. */
function records_contrast(string $a, string $b): float
{
    $lum = function (string $hex): float {
        $l = 0.0;
        foreach (array_combine([0.2126, 0.7152, 0.0722], sscanf($hex, '%02x%02x%02x')) as $w => $v) {
            $c = $v / 255;
            $l += (float)$w * ($c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
        }
        return $l;
    };
    [$hi, $lo] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];
    return ($hi + 0.05) / ($lo + 0.05);
}

/** A database date or date-time (Lagos time) as an Excel date. */
function records_excel_date(string $value, bool $date_only): ?float
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/', $value, $m) || $m[1] === '0000') {
        return null;
    }
    $serial = XlDate::formattedPHPToExcel((int)$m[1], (int)$m[2], (int)$m[3]);
    if (!$date_only && isset($m[4])) {
        $serial += ((int)$m[4] * 3600 + (int)$m[5] * 60 + (int)($m[6] ?? 0)) / 86400;
    }
    return (float)$serial;
}

/** A column header as a structured reference takes it: '[' ']' '#' and "'" escaped. */
function records_table_header(string $head): string
{
    return preg_replace("/([\\[\\]#'])/", "'$1", $head);
}

/** Text safe for a cell: no control characters, at most what Excel holds. */
function records_clean_text(string $text): string
{
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, 32000);
}

/** "Made 19 Oct 2026, 2:05 pm". */
function records_when(?string $db_time, bool $with_time = true): string
{
    $t = strtotime((string)$db_time) ?: time();
    return $with_time ? date('j M Y, g:i a', $t) : date('j M Y', $t);
}

/** "All records up to 19 Oct 2026", or "1 Jan 2026 to 30 Jun 2026". */
function records_period_text(array $book): string
{
    $from = $book['date_from'] ?? null;
    $to   = $book['date_to'] ?? null;
    $day  = fn($d) => date('j M Y', strtotime((string)$d));
    if (!$from && !$to) return 'All records up to ' . $day($book['created_at'] ?? 'now');
    if (!$from) return 'Records up to ' . $day($to);
    if (!$to) return 'Records from ' . $day($from) . ' to ' . $day($book['created_at'] ?? 'now');
    return 'Records from ' . $day($from) . ' to ' . $day($to);
}

/** "With Nfelia since 12 March 2025 · 1 year, 7 months". */
function records_since_text(string $joined, string $made): string
{
    $a = new DateTimeImmutable(substr($joined, 0, 10) ?: 'now');
    $b = new DateTimeImmutable(substr($made, 0, 10) ?: 'now');
    $d = $a->diff($b);
    $parts = [];
    if ($d->y) $parts[] = $d->y . ' year' . ($d->y === 1 ? '' : 's');
    if ($d->m) $parts[] = $d->m . ' month' . ($d->m === 1 ? '' : 's');
    if (!$parts) $parts[] = $d->days <= 1 ? 'since today' : $d->days . ' days';
    return 'With Nfelia since ' . $a->format('j F Y') . ' · ' . implode(', ', $parts);
}

/** What the business does, in words. */
function records_category_text(array $b): string
{
    $cat = ['fashion' => 'Fashion', 'beauty' => 'Hair and beauty', 'food_beverage' => 'Food and drinks', 'tech_repair' => 'Phones, tech and repairs', 'distribution' => 'Distribution',
            'services' => 'Services', 'wholesale_retail' => 'Wholesale and retail', 'creative' => 'Creative work', 'other' => ''][$b['category'] ?? 'other'] ?? '';
    $type = ['product' => 'sells products', 'service' => 'sells services', 'mixed' => 'sells products and services'][$b['business_type'] ?? ''] ?? '';
    return trim($cat . ($cat && $type ? ', ' : '') . ($cat ? $type : ucfirst($type)));
}

/** A social handle or link as a link. */
function records_social_url(string $site, string $handle): string
{
    if (preg_match('#^https?://#i', $handle)) return $handle;
    $h = ltrim(trim($handle), '@');
    return ['instagram' => 'https://instagram.com/', 'facebook' => 'https://facebook.com/', 'tiktok' => 'https://www.tiktok.com/@'][$site] . rawurlencode($h);
}

/**
 * The business's logo as a PNG or JPEG file Excel can show, or null. A WebP
 * logo is turned into a PNG first (Excel can't show WebP); the caller
 * deletes the copy.
 */
function records_logo_file(array $business): ?string
{
    $rel = ltrim((string)($business['logo_path'] ?? ''), '/');
    $file = $rel !== '' ? realpath(BASE_PATH . '/' . $rel) : false;
    if (!$file || !str_starts_with($file, realpath(BASE_PATH) . '/') || !is_file($file)) return null;
    $info = @getimagesize($file);
    if (!$info) return null;
    $make = match ($info['mime']) { 'image/png' => 'imagecreatefrompng', 'image/jpeg' => 'imagecreatefromjpeg', 'image/webp' => 'imagecreatefromwebp', 'image/gif' => 'imagecreatefromgif', default => null };
    if (!$make || !function_exists($make) || !($img = @$make($file))) return null;
    imagesavealpha($img, true);
    $copy = tempnam(sys_get_temp_dir(), 'nflogo') . '.png';
    imagepng($img, $copy);
    return $copy;
}
