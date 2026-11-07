# Notification System

Cross-system notification bell integrated into all FIMS modules via a single shared file.

---

## Overview

The notification system is centralized in **UAC** and exposed through its API. Any FIMS system (ACCOUNTING-GAD, ACCOUNTING-BAPD, etc.) that includes the shared `notification.php` file automatically gets a working notification bell in the navbar — no per-system configuration required.

```
┌──────────────────────────────────────────────────────────────┐
│                    Browser (any system)                      │
│                                                              │
│   _shared/notification.php  →  Bell UI + NotificationManager│
│              │                                               │
│              │  fetch (credentials: include, X-Authorization)│
│              ▼                                               │
│   http://nha.localhost/UAC/api/NotificationManagers/*        │
│              │                                               │
│              ▼                                               │
│   UAC/app/routes/Notification.php  →  Session auth          │
│              │                                               │
│              ▼                                               │
│   UAC/app/services/NotificationManager.php  →  PostgreSQL    │
└──────────────────────────────────────────────────────────────┘
```

---

## File Locations

| File | Purpose |
|------|---------|
| `_shared/notification.php` | Global navbar bell — include this in every system layout |
| `UAC/app/routes/Notification.php` | Slim API route handlers |
| `UAC/app/services/NotificationManager.php` | PHP service — send, fetch, mark read |
| `UAC/app/lib/NotificationManager.php` | Library helpers (EMQX/MQTT publishing) |
| `UAC/notification-test.php` | Browser-based test suite |

---

## API Endpoints

All endpoints live under `/UAC/api/`.

### GET `/NotificationManagers/recent`
Returns the last 20 notifications for the logged-in user, ordered by unread first then by date descending.

**Auth:** Session cookie (no JWT required)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "app_667abc123.456",
      "subject": "JEV #2024-001 requires your approval",
      "body": "A new JEV has been submitted.",
      "link": "/ACCOUNTING-GAD/JevListing",
      "type": "user",
      "from_system": "ACCOUNTING-GAD",
      "time_ago": "5 minutes ago",
      "read_by_user": false
    }
  ],
  "unread_count": 3
}
```

---

### POST `/NotificationManagers/mark-read`
Marks a single notification as read for the current user.

**Auth:** Session cookie (no JWT required)

**Request body:**
```json
{ "id": "app_667abc123.456" }
```

**Response:**
```json
{ "success": true }
```

---

### POST `/NotificationManagers/mark-all-read`
Marks all notifications as read for the current user.

**Auth:** Session cookie (no JWT required)

**Response:**
```json
{ "success": true }
```

---

### POST `/NotificationManagers/send`
Sends a notification to a target (user, office, user level, or broadcast).

**Auth:** Session cookie (no JWT required)

**Request body:**
```json
{
  "type": "user",
  "target": "42",
  "subject": "JEV #2024-001 requires your approval",
  "body": "A new JEV has been submitted and is awaiting your review.",
  "link": "/ACCOUNTING-GAD/JevListing",
  "from_system": "ACCOUNTING-GAD"
}
```

**Supported `type` values:**

| Type | Target value | Description |
|------|-------------|-------------|
| `user` | numeric `user_id` | Send to a specific account |
| `all` | *(leave blank)* | Broadcast to all users |
| `userlevel` | numeric `user_level_id` | All users of a given level |
| `office` | numeric `office_id` | All users in an office |
| `usergroup` | numeric `reports_to_user_id` | All users under a supervisor |
| `system` | numeric `system_id` | All users assigned to a system |

**Supported `from_system` values:**
`UAC`, `ACCOUNTING-GAD`, `ACCOUNTING-BAPD`, `ACCOUNTING-DCD`, `FMD`, `TREASURY`

**Response:**
```json
{ "success": true, "message": "NotificationManager sent successfully" }
```

---

## Authentication — How It Works

### Why session-based (not JWT)

The notification bell is a **global shared component** — it loads on every page across all systems. Each system issues its own JWT that UAC does not recognize, so Bearer token auth breaks cross-system. Session cookies, however, are shared across all subpaths of `nha.localhost` by the browser automatically.

### The `UseSession()` trigger

UAC's Slim API only starts the PHP session when the incoming request includes **at least one** of:
- A CSRF token field in the body or query params
- The `X-Authorization` header (any value, including empty)

Without the header, `$_SESSION` is never loaded → `CurrentUserID()` returns null → 401.

**Every fetch to UAC's API must include `X-Authorization: Bearer `** — even with an empty value.

```javascript
// ✅ Correct — session will be initialized
fetch('/UAC/api/NotificationManagers/recent', {
    credentials: 'include',
    headers: { 'X-Authorization': 'Bearer ' }
});

// ❌ Wrong — session never starts, always 401
fetch('/UAC/api/NotificationManagers/recent', {
    credentials: 'include'
});
```

### Prerequisite: UAC session must exist

The user must have `UAC_Status_UserID` in their PHP session. This is set when they log into UAC. If a user is only logged into another system (e.g., ACCOUNTING-GAD) and has never authenticated through UAC, the notification API returns 401.

---

## Frontend Integration (`_shared/notification.php`)

### How to include

Add this line in any system's layout/header file:

```php
<?php include dirname(__DIR__, N) . '/_shared/notification.php'; ?>
```

Where `N` is the number of directory levels up to reach the project root. For example, from `ACCOUNTING-GAD/app/layout/`:

```php
<?php include dirname(__DIR__, 3) . '/_shared/notification.php'; ?>
```

### What it renders

The file injects a `<li>` dropdown into the PHPMaker navbar via:

```html
<script type="text/html" class="ew-js-template"
    data-name="notificationsDropdown"
    data-method="appendTo"
    data-target="#ew-navbar-end"
    data-seq="9">
```

No manual HTML placement needed — PHPMaker's template engine picks it up automatically.

### `NotificationManager` object

The JS object is attached to `window.NotificationManager` and is safe to call from any page:

```javascript
// Force refresh the bell
window.NotificationManager.updateNotifications();

// Mark all read (same as clicking the button)
window.NotificationManager.markAllRead(event);
```

### Auto-refresh interval

The bell polls every **30 seconds** and also refreshes when:
- The page becomes visible again (after tab switch / window focus)
- An `appNotification` DOM event is dispatched (MQTT/real-time)

---

## Sending Notifications from PHP (Server-side)

Use the `NotificationManager` service directly from any system's PHP code. Include UAC's autoloader or call the UAC API via HTTP.

### Via UAC API (recommended for non-UAC systems)

```php
$payload = [
    'type'        => 'user',
    'target'      => (string) $approverId,
    'subject'     => 'JEV #' . $jevNumber . ' awaits your approval',
    'body'        => 'A JEV submitted by ' . $preparedBy . ' needs your review.',
    'link'        => '/ACCOUNTING-GAD/JevView?jev_number=' . $jevNumber,
    'from_system' => 'ACCOUNTING-GAD',
];

$ch = curl_init('http://nha.localhost/UAC/api/NotificationManagers/send');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Authorization: Bearer '],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIE         => 'PHPSESSID=' . session_id(),
]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### Via NotificationManager directly (if in UAC context)

```php
use PHPMaker2024\UAC\NotificationManager;

// To a specific user
NotificationManager::sendToUser($userId, $subject, $body, $link, 'ACCOUNTING-GAD');

// To all users in an office
NotificationManager::sendToOffice($officeId, $subject, $body, $link, 'ACCOUNTING-GAD');

// To a user level
NotificationManager::sendToUserLevel($levelId, $subject, $body, $link, 'ACCOUNTING-GAD');

// Broadcast to everyone
NotificationManager::sendToAll($subject, $body, $link, 'ACCOUNTING-GAD');
```

---

## Testing

Open the test suite in your browser while logged into UAC:

```
http://nha.localhost/UAC/notification-test.php
```

### Automated tests (click ▶ Run All Tests)

| # | Test | What it checks |
|---|------|----------------|
| 1 | Session Cookie Present | Browser has a valid UAC session cookie |
| 2 | GET /recent | Returns 200 + notifications array; auto-fills Notification ID |
| 3 | POST /send (to user) | Sends a real test notification to the target entered in the form |
| 4 | POST /mark-read | Marks the auto-filled notification ID as read |
| 5 | POST /mark-all-read | Marks all notifications read |
| 6 | Verify unread = 0 | Re-fetches and confirms `unread_count` dropped to 0 |

### Send form (manual)

The lower section lets you send a notification to a specific account interactively:

1. Set **Type** to `user`
2. Enter the numeric **Target** (user ID)
3. Fill in **Subject** and **Body**
4. Choose **From System**
5. Click **📤 Send Notification**

The response panel shows the full request payload and API response.

### Common failures

| Error | Cause | Fix |
|-------|-------|-----|
| `401 Not authenticated` | No UAC session, or `X-Authorization` header missing | Log into UAC first; ensure header is included in fetch |
| `400 Invalid system code` | `from_system` not in the allowed list | Use a valid system code from the list above |
| `400 Target is required` | `type` is not `all` but `target` was omitted | Fill in the Target field |
| Bell spinner never stops | JS error or network block | Check browser console for fetch errors |

---

## Database

Notifications are stored in the `notifications` table in UAC's database.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `varchar` | `uniqid()` prefixed with `app_` |
| `type` | `varchar` | `all`, `user`, `userlevel`, `office`, `usergroup`, `system` |
| `target` | `varchar` | Target ID matching the type |
| `user_id` | `int` | Sender's user ID (nullable) |
| `subject` | `text` | Notification title |
| `body` | `text` | Notification body |
| `link` | `varchar` | URL the bell click navigates to (nullable) |
| `from_system` | `varchar` | Source system code |
| `read_users` | `text` | Comma-separated list of user IDs who have read it |
| `timestamp` | `timestamptz` | When the notification was created |

`read_users` is a comma-separated string instead of a junction table, keeping the schema simple for broadcast notifications where many users may read the same row.

---

## Real-time (MQTT / EMQX)

When a notification is sent, UAC also publishes it to EMQX via the REST API on topic:

```
lirmds/{type}/{target}
```

Frontend clients subscribed to this topic receive the notification instantly without waiting for the 30-second poll. The `appNotification` DOM event triggers `NotificationManager.updateNotifications()` when an MQTT message arrives.

EMQX credentials are configured in `UAC/src/config.php` under the `EMQX` key.
