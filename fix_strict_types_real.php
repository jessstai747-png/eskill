<?php
$dirs = ['app', 'bin', 'tests'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $info) {
        if ($info->isFile() && $info->getExtension() === 'php') {
            $path = $info->getPathname();
            $content = file_get_contents($path);
            if (strpos($content, 'strict_types') === false && strpos($content, '<?php') !== false) {
                // we ONLY replace at the very start of the file
                $content = preg_replace('/<\?php\s+/', "<?php\n\ndeclare(strict_types=1);\n\n", $content, 1);
                file_put_contents($path, $content);
                echo "Fixed $path\n";
            }
        }
    }
}
