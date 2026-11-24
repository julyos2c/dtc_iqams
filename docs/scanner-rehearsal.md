# Phase 3 scanner rehearsal

## Local setup

Use a disposable local database and a persistent browser profile. Apply the application's migrations. For a database already at Phase 2, the additive migration is `2026_11_24_000001_create_scanner_rehearsal_transport`. Enable both `SCANNER_QUEUE_PREVIEW=true` and `SCANNER_REHEARSAL_SYNC=true` and clear cached Laravel configuration. Both flags default to false. The server permits rehearsal only in the local and testing environments.

1. Open IQAMS on HTTPS or localhost. Sign in as a scanner operator, select the active terminal and open the preview. Confirm the operator's password in the linked tab, then select **Pair device**. Pairing and revocation also require the scanner security management permission.
2. Scan two synthetic cards such as `TEST-001` and `TEST-002`. A local capture is acknowledged after its IndexedDB transaction commits. Inspect `scanner_rehearsal_receipts` in the disposable database for server outcomes. The canonical attendance table should have the same row count as before testing.
3. Disconnect after initialization, scan again, reopen the preview and reconnect. The original operator can sign in again and resume. A capture made after restarting without a new trusted time anchor should have a review receipt. Confirm the password again when uploading an expired lease if the confirmation has timed out.
4. Use **Retry synchronization** to retry pending events. The sender respects server throttling. Use **Revoke device** to retire the terminal pairing and its leases. Pending data stays in the browser. An authorized administrator can then pair a new key. Turn the flags off and clear cached configuration to return to the existing scanner.

## Protocol

All transport routes are same-origin JSON POSTs below `/attendance-scanner/rehearsal/`, within the scanner's authenticated web middleware group. The uncached preview context supplies CSRF and settings. The browser keeps CSRF in memory. Use a fresh challenge for every signed request, including retries.

| Route | Input and result |
|---|---|
| `challenge` | A purpose of pair, lease, heartbeat or sync. Returns a random nonce valid for one minute, scoped to the login session, operator, terminal and purpose. |
| `pair` | Base64 DER SubjectPublicKeyInfo for a 2048-bit RSA key. The signature proves possession of the private key. Returns the device fingerprint. |
| `lease` | Device fingerprint and page clock UUID. Returns the offline session UUID, terminal ID, server time anchor in milliseconds and expiry in Unix seconds. |
| `heartbeat` | Device, session ID and last observed pending count. Returns server time, device and terminal status, lease validity, contiguous receipt sequence and synchronization availability. The terminal row stores observation time and count. |
| `sync` | Device, session ID and up to 25 events. Returns indexed per-event results, session ID, rehearsal mode and contiguous receipt sequence. |
| `revoke` | Device fingerprint and optional session ID. Requires password confirmation and scanner security permission. The selected terminal bounds the operation. Omitting the session revokes the device and all its leases. |

For signed actions send `X-Scanner-Challenge` and `X-Scanner-Signature`. The signature is base64 RSASSA-PKCS1-v1_5/SHA-256 over the UTF-8 nonce, purpose and lowercase SHA-256 hex digest of the exact request body, joined by newlines.

Example sync body

```json
{
  "device": "<fingerprint>",
  "session_id": "<offline session UUID>",
  "events": [{
    "event_uuid": "<event UUID>",
    "sequence": 1,
    "captured_at": "2026-11-24T03:05:00.000Z",
    "clock_id": "<page clock UUID>",
    "elapsed_ms": 1200,
    "qr_code": "TEST-001"
  }]
}
```

Example response

```json
{
  "mode": "rehearsal",
  "session_id": "<offline session UUID>",
  "receipts": [{"event_uuid":"<event UUID>","receipt_id":"receipt-1","status":"synchronized","reason":null,"index":0}],
  "ack_sequence": 1
}
```

A receipt can also be rejected or require review. A malformed event receives an indexed rejection with a null receipt ID and stays recoverable on the client. Requests exceeding the batch limit receive HTTP 422. A failed transaction has no acknowledged results. Reissue a fresh signed request with the original event UUIDs and capture evidence. Identical valid retries return the saved receipt. Conflicting UUID or sequence claims receive separate durable conflict receipts.

## Data and trust boundaries

The migration adds `scanner_devices`, `scanner_device_challenges`, `scanner_offline_sessions`, `scanner_rehearsal_receipts` and `scanner_receipt_conflicts`. Terminal columns record the last heartbeat, its reported queue size and last rehearsal sync. Locks serialize terminal processing. Unique constraints protect the event UUID and session sequence independently. Acknowledgment means the server holds contiguous receipts, including review or rejection decisions. It does not establish attendance or completion of absence reconciliation.

The browser stores a non-exportable private CryptoKey and acquires a Web Lock for each send. A lost key requires authorized re-pairing. Earlier Phase 2 records lack a paired device and session sequence and stay isolated from uploads. Device revocation, browser storage eviction and deletion of a profile can require manual investigation. A valid device signature attests to the submitting key. A compromised browser, copied card or manipulated clock can still supply false evidence.

Default intervals are 30 seconds for heartbeat, 90 seconds for suspected disconnect, eight hours for capture authorization and one operating day for escalation. The latter two monitoring policies for suspicion and escalation are reserved for subsequent phases. The clock discrepancy tolerance defaults to 120 seconds. IndexedDB uses local sequence for upload order and a per-lease sequence for server acknowledgment. New pages get a new monotonic clock identifier. Offline captures without a matching anchor go to review.

## Verification

Run the scanner feature tests, `node --test tests/js/*.test.js`, and `node --test tests/browser/scanner-preview.mjs tests/browser/scanner-rehearsal.mjs`. Browser tests use disposable profiles. The paired test also creates an isolated SQLite database and a loopback Laravel server. Set `CHROME_BIN` if Chrome is installed elsewhere.

The opt-in MySQL test is `tests/Integration/ScannerReceiptConcurrencyTest.php`. It requires a migrated disposable MySQL database named `iqams_phase3_disposable` on `127.0.0.1:13328`, the testing environment, and MySQL settings inherited by the two PHP workers. On Windows, point `OPENSSL_CONF` to the PHP installation's OpenSSL configuration for key export.

Two existing QR controller response tests expect a third profile detail for the student's year level and the instructor's role. Phase 2 reproduced both failures using the original controller. Phase 3 preserves the existing profile response and assertions.

A fresh Oracle MySQL 8.4.3 bootstrap encountered error 1215 in the earlier `2026_11_06_000000_add_qr_management_constraints` migration. The Phase 3 migration applied independently against the disposable database with its referenced base tables. Rehearsal introduces no historical eligibility decisions, attendance writes or absence reconciliation.
