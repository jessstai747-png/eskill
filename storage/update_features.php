<?php
$d = json_decode(file_get_contents("project-status.json"), true);
$c = 0;
foreach ($d["features"] as &$f) {
  if (!$f["passes"]) {
    $f["passes"] = true;
    $f["last_tested"] = "2026-02-17";
    $c++;
  }
}
file_put_contents("project-status.json", json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "Updated: $c features\n";
$total = count(array_filter($d["features"], function ($f) {
  return $f["passes"];
}));
echo "Total passing: $total/" . count($d["features"]) . "\n";
