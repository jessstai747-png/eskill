<?php
$json = json_decode(file_get_contents("project-status.json"), true);
foreach ($json["features"] as $f) {
    if (!$f["passes"]) {
        $desc = $f["description"] ?? "?";
        $files = implode(", ", $f["files"] ?? []);
        echo $f["id"] . " | " . $desc . " | " . $files . "\n";
    }
}
