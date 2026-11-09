<?php

// Порог покрытия строк для CI: php scripts/coverage-check.php build/clover.xml 90
[$_, $file, $min] = $argv + [null, 'build/clover.xml', '90'];
$xml = simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Нет отчёта покрытия: {$file}\n");
    exit(1);
}
$m = $xml->project->metrics;
$total = (int) $m['statements'];
$covered = (int) $m['coveredstatements'];
$pct = $total ? round($covered / $total * 100, 2) : 0;
echo "Покрытие строк: {$pct}% ({$covered}/{$total}), порог {$min}%\n";
exit($pct >= (float) $min ? 0 : 1);
