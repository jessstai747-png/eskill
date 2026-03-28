<?php
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/'));
$files = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    if (strpos($content, 'strict_types') === false) {
        $content = preg_replace('/<\?php\s+/', "<?php\n\ndeclare(strict_types=1);\n\n", $content, 1);
        file_put_contents($path, $content);
        echo "Fixed $path\n";
    }
}
