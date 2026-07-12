from pathlib import Path

routes = Path(__file__).resolve().parents[2] / "app" / "Routes" / "web.php"
text = routes.read_text(encoding="utf-8")

checks = {
    "dashboard competitors uses DashboardController HTML action": r"$router->get('dashboard/competitors', 'App\Controllers\DashboardController', 'competitors');",
    "api competitors list uses CompetitorController JSON action": r"$router->get('api/competitors', 'App\Controllers\CompetitorController', 'index');",
    "api competitors add uses CompetitorController JSON action": r"$router->post('api/competitors', 'App\Controllers\CompetitorController', 'add');",
    "api competitors remove uses CompetitorController JSON action": r"$router->delete('api/competitors/{sellerId}', 'App\Controllers\CompetitorController', 'remove');",
}

missing = [label for label, needle in checks.items() if needle not in text]
if missing:
    raise SystemExit("Competitors route split is incomplete:\n- " + "\n- ".join(missing))

print("Competitors dashboard/API routes are split correctly.")
