from pathlib import Path

view = Path(__file__).resolve().parents[2] / "app" / "Views" / "dashboard" / "orders-content.php"
text = view.read_text(encoding="utf-8")

checks = {
    "orders list requests local cache fallback": "allow_local_cache: 'true'",
    "order detail requests local cache fallback": "`/api/orders/${orderId}?allow_local_cache=true`",
}

missing = [label for label, needle in checks.items() if needle not in text]
if missing:
    raise SystemExit("Orders dashboard local fallback is incomplete:\n- " + "\n- ".join(missing))

print("Orders dashboard requests local cache fallback for list and detail endpoints.")
