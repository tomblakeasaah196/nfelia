<?php
/**
 * The period a report covers (app/helpers/report_period.php), and how money
 * in less money out reads on Home and the profit and loss (money_left_reads()
 * in app/helpers/money.php). Pure dates and sums, no database, so it runs on
 * its own:
 *   php tests/reports/period_test.php
 * What counts as money out is pinned against the database in money_test.php.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../app/helpers/report_period.php';
require __DIR__ . '/../../app/helpers/money.php';

$fails = 0;
function same(string $what, $want, $got): void {
    global $fails;
    if ($want === $got) {
        echo "  ok  {$what}\n";
        return;
    }
    $fails++;
    echo "FAIL  {$what}\n      want " . var_export($want, true) . "\n      got  " . var_export($got, true) . "\n";
}
function at(string $day): DateTimeImmutable { return new DateTimeImmutable($day); }
function span(array $p): string { return "{$p['start']}..{$p['end']} vs {$p['prev_start']}..{$p['prev_end']}"; }

$today = at('2026-10-19');

$p = report_period([], $today);
same('nothing chosen is this month, so far', '2026-10-01..2026-10-19 vs 2026-09-01..2026-09-19', span($p));
same('this month reads as the month', 'This month: October 2026', $p['button']);
same('this month compares day for day', '1 to 19 Sep 2026', $p['prev_label']);
same('a month or less charts by day', 'day', $p['by']);

$p = report_period(['period' => 'last_month'], $today);
same('last month is whole, against the whole month before', '2026-09-01..2026-09-30 vs 2026-08-01..2026-08-31', span($p));
same('a whole month compares by name', 'August 2026', $p['prev_label']);

$p = report_period(['period' => 'this_quarter'], $today);
same('this quarter starts in October', '2026-10-01..2026-10-19 vs 2026-09-01..2026-09-19', span($p));
$p = report_period(['period' => 'this_quarter'], at('2026-08-15'));
same('a quarter part way through', '2026-07-01..2026-08-15 vs 2026-05-01..2026-06-15', span($p));
same('two months chart by month', 'month', $p['by']);
same('a quarter so far reads as its months', 'Jul to Aug 2026', $p['label']);

$p = report_period(['period' => 'year_to_date'], $today);
same('year to date is against the same dates last year', '2026-01-01..2026-10-19 vs 2025-01-01..2025-10-19', span($p));
same('', '1 Jan to 19 Oct 2025', $p['prev_label']);

$p = report_period(['period' => 'last_12_months'], $today);
same('last 12 months runs to today', '2025-11-01..2026-10-19 vs 2024-11-01..2025-10-19', span($p));
same('across a new year the label carries both years', 'Nov 2025 to Oct 2026', $p['label']);

$p = report_period(['from' => '2026-01', 'to' => '2026-06'], $today);
same('a month range', '2026-01-01..2026-06-30 vs 2025-07-01..2025-12-31', span($p));
same('a month range button is just the range', 'Jan to Jun 2026', $p['button']);
same('a past range compares by months', 'Jul to Dec 2025', $p['prev_label']);
same('the range is remembered for the picker', ['2026-01', '2026-06'], [$p['from'], $p['to']]);

$p = report_period(['from' => '2026-06', 'to' => '2026-01'], $today);
same('a range picked backwards is turned round', '2026-01-01..2026-06-30', "{$p['start']}..{$p['end']}");

$p = report_period(['from' => '2026-08', 'to' => '2027-03'], $today);
same('a range into the future stops at today', '2026-08-01..2026-10-19 vs 2026-05-01..2026-07-19', span($p));

$p = report_period(['from' => '2026-03', 'to' => '2026-03'], $today);
same('one month as a range', 'March 2026', $p['label']);
same('', '2026-02-01..2026-02-28', "{$p['prev_start']}..{$p['prev_end']}");

$p = report_period(['month' => '6', 'year' => '2026'], $today);
same('old month and year links still open their month', '2026-06-01..2026-06-30', "{$p['start']}..{$p['end']}");

foreach ([['period' => 'everything'], ['from' => 'abc'], ['from' => '2019-12', 'to' => '2019-12'], ['from' => '2026-13']] as $junk) {
    same('nonsense falls back to this month: ' . http_build_query($junk), 'this_month', report_period($junk, $today)['key']);
}

$p = report_period(['period' => 'this_month'], at('2026-03-31'));
same('31 Mar compares with all of Feb', '2026-02-01..2026-02-28', "{$p['prev_start']}..{$p['prev_end']}");

same('day buckets', ['2026-10-01' => '1 Oct', '2026-10-02' => '2 Oct'],
    report_period_buckets(['start' => '2026-10-01', 'end' => '2026-10-02', 'by' => 'day']));
same('month buckets across a year carry the year', ['2025-12' => 'Dec 25', '2026-01' => 'Jan 26'],
    report_period_buckets(['start' => '2025-12-01', 'end' => '2026-01-15', 'by' => 'month']));

same('change up', ['up', '50%'], report_period_change(150, 100));
same('change down', ['down', '25%'], report_period_change(75, 100));
same('change from a loss is measured from its size', ['up', '200%'], report_period_change(100, -100));
same('nothing before, nothing to compare', null, report_period_change(100, 0));

// Cash in, cash out (Tom's decision, October 2026): stock bought is money out,
// but a gap it covers is money put into stock, not a loss
same('more came in than went out', 'up', money_left_reads(500000, 300000, 200000));
same('exactly even is not a gap', 'up', money_left_reads(300000, 300000, 0));
same('nothing in, nothing out', 'up', money_left_reads(0, 0, 0));
same('a restock week: the gap is all stock', 'stock', money_left_reads(200000, 500000, 400000));
same('stock covering the gap to the kobo', 'stock', money_left_reads(100.10, 300.30, 200.20));
same('stock covers only part of the gap', 'down', money_left_reads(200000, 500000, 100000));
same('more went out, and none of it on stock', 'down', money_left_reads(100000, 150000, 0));
same('nothing came in but stock was bought', 'stock', money_left_reads(0, 180000, 180000));

echo $fails ? "\n{$fails} failed\n" : "\nall passed\n";
exit($fails ? 1 : 0);
