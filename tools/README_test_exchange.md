Test exchange flow helper

This helper simulates a simple exchange lifecycle to test the mutual-confirmation logic.

How to run (PowerShell on Windows):

1. Open a PowerShell in the project root (c:\xampp\htdocs\item-exchange)
2. Run:

```powershell
php .\tools\test_exchange_flow.php
```

What the script does:
- Creates two test users and two test items
- Inserts a pending exchange request
- Simulates owner choosing delivery and confirming
- Simulates requester confirming
- If both confirmations are set, sets status to `accepted` and marks items `is_available = 0`

Notes:
- This script WILL insert rows into your DB. It does not clean up after itself. Remove test rows manually if desired.
- If your DB user/password or database name differ, edit the connection parameters at the top of `test_exchange_flow.php`.

Cleanup
- Remove the test rows (users, items, exchange_requests, reviews) that the script created. You can identify them by username `test_requester` and `test_owner` or by recent created_at timestamps.

If you want, I can add an automatic cleanup option to the script (delete inserted rows at the end).