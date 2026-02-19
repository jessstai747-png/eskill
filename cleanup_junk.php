<?php
$dir = '/home/eskill/htdocs/eskill.com.br';
$count = 0;
foreach (scandir($dir) as $f) {
    if ($f === '.' || $f === '..') continue;
    $c = ord($f[0]);
    // Keep files starting with a-z, A-Z, dot, or underscore
    if (($c >= 65 && $c <= 90) || ($c >= 97 && $c <= 122) || $c === 46 || $c === 95) continue;
    $path = $dir . '/' . $f;
    if (is_file($path)) {
        @unlink($path);
        echo "Removed: $f\n";
        $count++;
    }
}
echo "Total removed: $count\n";
