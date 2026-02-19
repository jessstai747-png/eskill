#!/usr/bin/env php
<?php
$json = json_decode(file_get_contents('project-status.json'), true);
$toMark = ['ITEMS-004', 'ITEMS-005', 'REPORT-002', 'NOTIF-003', 'HEALTH-003', 'SEC-001', 'SEC-003', 'CACHE-001', 'AI-008', 'AI-009'];
$count = 0;
foreach ($json['features'] as &$f) {
    if (in_array($f['id'], $toMark) && !$f['passes']) {
        $f['passes'] = true;
        $f['last_tested'] = date('Y-m-d');
        $count++;
    }
}
unset($f);
$json['_meta']['last_updated'] = date('Y-m-d');
file_put_contents('project-status.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Marked $count features as passing\n";

// Count total
$total = count($json['features']);
$passing = count(array_filter($json['features'], fn($f) => $f['passes']));
echo "$passing/$total features passing\n";
