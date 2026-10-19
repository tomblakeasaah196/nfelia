<?php
/**
 * Nfelia — the period a report covers, read from the address bar.
 *
 *   ?period=this_month | last_month | this_quarter | year_to_date | last_12_months
 *   ?from=2026-01&to=2026-06      whole months, either order
 *   ?month=6&year=2026            the old single-month links still open
 *
 * Periods are whole months. One that runs into the future is cut at today,
 * so "This month" on the 19th is the 1st to the 19th, and it is compared
 * with the 1st to the 19th of the month before, not a whole month.
 *
 * The comparison is the stretch of the same length just before, except Year
 * to date, which is compared with the same dates last year (Jan to Oct this
 * year against Mar to Dec last year would say nothing).
 */

const REPORT_PERIOD_PRESETS = [
    'this_month'     => 'This month',
    'last_month'     => 'Last month',
    'this_quarter'   => 'This quarter',
    'year_to_date'   => 'Year to date',
    'last_12_months' => 'Last 12 months',
];

/** The earliest month a report can start, as the old year picker allowed. */
const REPORT_PERIOD_FIRST_YEAR = 2020;

/**
 * @return array{key:string, preset:string, start:string, end:string, months:int,
 *   label:string, button:string, filename:string, by:string,
 *   prev_start:string, prev_end:string, prev_label:string,
 *   from:string, to:string}
 */
function report_period(array $query, ?DateTimeImmutable $today = null): array {
    $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
    $this_month = $today->modify('first day of this month');

    $preset = (string)($query['period'] ?? '');
    $from = $to = null;

    if (isset($query['from']) || isset($query['to'])) {
        $from = report_period_month((string)($query['from'] ?? ''), $this_month);
        $to   = report_period_month((string)($query['to'] ?? ''), $this_month);
        $from ??= $to;
        $to   ??= $from;
        $preset = $from ? 'custom' : '';
    } elseif (isset($query['month']) || isset($query['year'])) {
        $m = max(1, min(12, (int)($query['month'] ?? $today->format('n'))));
        $y = (int)($query['year'] ?? $today->format('Y'));
        $from = $to = report_period_month(sprintf('%04d-%02d', $y, $m), $this_month);
        $preset = $from ? 'custom' : '';
    }

    switch ($preset) {
        case 'custom':
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            break;
        case 'last_month':
            $from = $to = $this_month->modify('-1 month');
            break;
        case 'this_quarter':
            $q_first = intdiv((int)$this_month->format('n') - 1, 3) * 3 + 1;
            $from = $this_month->setDate((int)$this_month->format('Y'), $q_first, 1);
            $to   = $this_month;
            break;
        case 'year_to_date':
            $from = $this_month->setDate((int)$this_month->format('Y'), 1, 1);
            $to   = $this_month;
            break;
        case 'last_12_months':
            $from = $this_month->modify('-11 months');
            $to   = $this_month;
            break;
        default:
            $preset = 'this_month';
            $from = $to = $this_month;
    }

    $months = ((int)$to->format('Y') - (int)$from->format('Y')) * 12 + (int)$to->format('n') - (int)$from->format('n') + 1;
    $start  = $from;
    $end    = $to->modify('last day of this month');
    $cut    = $end > $today;
    if ($cut) {
        $end = $today;
    }

    // A preset that lands on one whole month says so: "Last month" on 1 Nov
    // and a custom Jan to Jan both read as that month.
    $label = report_period_label($start, $to, $months);

    $back = $preset === 'year_to_date' ? 12 : $months;
    $prev_start = $start->modify("-{$back} months");
    $prev_end   = report_period_shift_end($end, $back, !$cut);

    return [
        'key'        => $preset === 'custom' ? 'custom' : $preset,
        'preset'     => REPORT_PERIOD_PRESETS[$preset] ?? 'Month range',
        'start'      => $start->format('Y-m-d'),
        'end'        => $end->format('Y-m-d'),
        'months'     => $months,
        'label'      => $label,
        'button'     => ($preset === 'custom' ? '' : REPORT_PERIOD_PRESETS[$preset] . ': ') . $label,
        'filename'   => preg_replace('/[^A-Za-z0-9]+/', '_', $label),
        'by'         => (int)$end->diff($start)->days < 31 ? 'day' : 'month',
        'prev_start' => $prev_start->format('Y-m-d'),
        'prev_end'   => $prev_end->format('Y-m-d'),
        'prev_label' => report_period_span_label($prev_start, $prev_end),
        'from'       => $from->format('Y-m'),
        'to'         => $to->format('Y-m'),
    ];
}

/** "2026-06" as the first of that month, or null if it isn't a month a report can show. */
function report_period_month(string $ym, DateTimeImmutable $this_month): ?DateTimeImmutable {
    if (!preg_match('/^(\d{4})-(\d{1,2})$/', trim($ym), $m)) {
        return null;
    }
    $year = (int)$m[1];
    $month = (int)$m[2];
    if ($month < 1 || $month > 12 || $year < REPORT_PERIOD_FIRST_YEAR) {
        return null;
    }
    $first = $this_month->setDate($year, $month, 1);
    return $first > $this_month ? $this_month : $first;
}

/** "June 2026", "Jan to Jun 2026", "Nov 2025 to Oct 2026". */
function report_period_label(DateTimeImmutable $from, DateTimeImmutable $to, int $months): string {
    if ($months === 1) {
        return $from->format('F Y');
    }
    if ($from->format('Y') === $to->format('Y')) {
        return $from->format('M') . ' to ' . $to->format('M Y');
    }
    return $from->format('M Y') . ' to ' . $to->format('M Y');
}

/**
 * The comparison period in words. Whole months read as months; a stretch cut
 * short reads day-first: "1 to 19 Sep 2026", "1 Jan to 19 Oct 2025".
 */
function report_period_span_label(DateTimeImmutable $start, DateTimeImmutable $end): string {
    $last_of_end = $end->modify('last day of this month');
    if ($start->format('j') === '1' && $end->format('Y-m-d') === $last_of_end->format('Y-m-d')) {
        $months = ((int)$end->format('Y') - (int)$start->format('Y')) * 12 + (int)$end->format('n') - (int)$start->format('n') + 1;
        return report_period_label($start, $end, $months);
    }
    if ($start->format('Y-m') === $end->format('Y-m')) {
        return $start->format('j') . ' to ' . $end->format('j M Y');
    }
    if ($start->format('Y') === $end->format('Y')) {
        return $start->format('j M') . ' to ' . $end->format('j M Y');
    }
    return $start->format('j M Y') . ' to ' . $end->format('j M Y');
}

/**
 * $end moved back $months months. A month's last day stays a last day
 * (30 Sep back one month is 31 Aug); any other day keeps its number, or the
 * month's last day where it has none (31 Mar back one month is 28 Feb).
 */
function report_period_shift_end(DateTimeImmutable $end, int $months, bool $whole): DateTimeImmutable {
    $first = $end->modify('first day of this month')->modify("-{$months} months");
    $last  = $first->modify('last day of this month');
    if ($whole) {
        return $last;
    }
    $day = min((int)$end->format('j'), (int)$last->format('j'));
    return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), $day);
}

/**
 * Chart buckets for a period: ['2026-10-01' => '1 Oct', ...] by day,
 * ['2026-06' => 'Jun', ...] by month (with the year once it changes).
 */
function report_period_buckets(array $period): array {
    $start = new DateTimeImmutable($period['start']);
    $end   = new DateTimeImmutable($period['end']);
    $out = [];
    if ($period['by'] === 'day') {
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $out[$d->format('Y-m-d')] = $d->format('j M');
        }
        return $out;
    }
    $span_years = $start->format('Y') !== $end->format('Y');
    for ($m = $start->modify('first day of this month'); $m <= $end; $m = $m->modify('+1 month')) {
        $out[$m->format('Y-m')] = $span_years ? $m->format('M y') : $m->format('M');
    }
    return $out;
}

/** "▲ 12%" as [direction, text] for a card, or null when there is nothing to compare with. */
function report_period_change(float $now, float $before): ?array {
    if ($before == 0.0) {
        return null;
    }
    $pct = (int)round(($now - $before) / abs($before) * 100);
    $dir = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
    return [$dir, abs($pct) . '%'];
}
