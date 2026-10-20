<?php
/** Small pieces shared by the error pages. */
if (!function_exists('ops_error_kind_chip')) {
    function ops_error_level_tone(string $level): string
    {
        return match ($level) { 'fatal', 'error' => 'bad', 'warning', 'slow' => 'warn', default => 'muted' };
    }
    function ops_error_status_chip(string $status): string
    {
        [$label, $tone] = match ($status) {
            'open'     => ['Open', 'accent'],
            'handling' => ['Being handled', 'warn'],
            'fixed'    => ['Fixed', 'ok'],
            default    => ['Ignored', 'muted'],
        };
        return '<span class="ops-chip ops-chip-' . $tone . '">' . e($label) . '</span>';
    }
    function ops_error_kind_chip(string $kind): string
    {
        return '<span class="ops-chip">' . e(['php' => 'Server', 'js' => 'Browser', 'csp' => 'Content policy', 'job' => 'Background job', 'log' => 'Server log', 'slow' => 'Slow page'][$kind] ?? $kind) . '</span>';
    }
    function ops_error_level_chip(string $level): string
    {
        $label = ['fatal' => 'Crash', 'error' => 'Error', 'warning' => 'Warning', 'notice' => 'Notice', 'deprecated' => 'Outdated code', 'slow' => 'Slow'][$level] ?? $level;
        return '<span class="ops-chip ops-chip-' . ops_error_level_tone($level) . '">' . e($label) . '</span>';
    }
    function ops_error_where(array $g): string
    {
        if ($g['kind'] === 'slow') return (string)$g['route'];
        return $g['file'] ? $g['file'] . ($g['line'] ? ':' . $g['line'] : '') : '';
    }
}
