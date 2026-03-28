<?php
$files = ['app/Controllers/AIPredictionsController.php', 'app/Controllers/PerformanceController.php', 'app/Controllers/CustomerController.php', 'app/Controllers/HealthController.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    // Remove header('Content-Type: application/json');
    $content = preg_replace("/\s*header\('Content-Type: application\/json'\);/", "", $content);
    // Replace http_response_code(XXX); echo json_encode(...); return; with $this->json(..., XXX);
    $content = preg_replace("/\s*http_response_code\((\d+)\);\s*echo json_encode\(([^;]+)\);\s*return;/", "\n        \$this->json($2, $1);", $content);
    // Replace echo json_encode(...); return; with $this->json(...);
    $content = preg_replace("/\s*echo json_encode\(([^;]+)\);\s*return;/", "\n        \$this->json($1);", $content);

    // Replace http_response_code(...); echo json_encode(...);
    $content = preg_replace("/\s*http_response_code\(([^)]+)\);\s*echo json_encode\(([^;]+)\);/", "\n        \$this->json($2, (int)($1));", $content);
    // Replace echo json_encode(...)
    $content = preg_replace("/\s*echo json_encode\(([^;]+)\);/", "\n        \$this->json($1);", $content);

    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
