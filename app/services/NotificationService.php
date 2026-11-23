<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Notification hub for schema uac — implements _shared/NOTIFICATION-IMPLEMENTATION-PLAN.md.
 *
 *  READ  : recent / unreadCount / markRead / markAllRead  (one audience query, §3.3)
 *  WRITE : send() + the toAll/toSystem/toUser/toUserLevel/toOffice/toUserGroup helpers (§5), and toRc() — everyone of a
 *          Responsibility Center (uac.users.rc_id), sent as one 'user' notification each.
 *          Business code calls the helpers; they never throw (return the id, or null).
 *  PUSH  : optional EMQX publish after the INSERT (§6); polling is the delivery guarantee.
 *  TEST  : sendTest() — one notification to the caller only, with a report of the INSERT and of what the broker answered.
 *
 * Audience types (the only vocabulary): all | system | user | userlevel | office | usergroup
 *   all       target 'all'
 *   system    uac.systems.system_id      — users with a uac.user_level_assignments row for that system
 *   user      uac.users.user_id
 *   userlevel uac.user_levels.user_level_id — users assigned that level (user_level_assignments)
 *   office    uac.offices.office_id      — users in uac.user_offices (active)
 *   usergroup supervisor's user_id       — users whose uac.users.reports_to_user_id = target
 */
class NotificationService
{
    public const TYPES = ['all', 'system', 'user', 'userlevel', 'office', 'usergroup'];

    /** Fallback when NOTIFY.ALLOWED_SYSTEMS is not configured. */
    private const DEFAULT_SYSTEMS = [
        'FIMS', 'UAC', 'ACCOUNTING-GAD', 'ACCOUNTING-BAPD', 'ACCOUNTING-DCD', 'TREASURY', 'FMD',
        'ACCOUNTING-PCDS', 'REGIONAL', 'DISTRICT',
    ];

    private const APP_CODE = 'FIMS';

    // ── Read side ────────────────────────────────────────────────

    /** SQL predicate: rows of uac.notifications (alias n) that $uid is in the audience of. */
    private static function audience(int $uid): string
    {
        return "(
              (n.type = 'all'       AND n.target = 'all')
           OR (n.type = 'user'      AND n.target = '{$uid}')
           OR (n.type = 'userlevel' AND n.target IN (SELECT user_level_id::text FROM uac.user_level_assignments WHERE user_id = {$uid}))
           OR (n.type = 'system'    AND n.target IN (SELECT system_id::text FROM uac.user_level_assignments WHERE user_id = {$uid} AND system_id IS NOT NULL))
           OR (n.type = 'office'    AND n.target IN (SELECT office_id::text FROM uac.user_offices WHERE user_id = {$uid} AND COALESCE(is_active, TRUE)))
           OR (n.type = 'usergroup' AND n.target = (SELECT reports_to_user_id::text FROM uac.users WHERE user_id = {$uid}))
        )";
    }

    /** The one function every read path uses. Unread first, newest first. */
    public function getUserNotifications(int $uid, int $limit = 10): array
    {
        $limit = max(1, min(200, $limit));
        $rows  = App\ExecuteRows(
            "SELECT n.id, n.type, n.target, n.subject, n.body, n.link, n.from_system, n.created_at,
                    (r.user_id IS NOT NULL) AS read_by_user
             FROM uac.notifications n
             LEFT JOIN uac.notification_reads r ON r.notification_id = n.id AND r.user_id = {$uid}
             WHERE " . self::audience($uid) . "
             ORDER BY (r.user_id IS NOT NULL) ASC, n.created_at DESC
             LIMIT {$limit}",
            'DB'
        ) ?: [];

        foreach ($rows as &$row) {
            $row['read_by_user'] = filter_var($row['read_by_user'], FILTER_VALIDATE_BOOLEAN);
            $row['time_ago']     = self::timeAgo((string) $row['created_at']);
        }

        return $rows;
    }

    public function recent(int $uid, int $limit = 10): array
    {
        return [
            'success'      => true,
            'data'         => $this->getUserNotifications($uid, $limit),
            'unread_count' => $this->unreadCount($uid),
        ];
    }

    public function unreadCount(int $uid): int
    {
        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM uac.notifications n
             WHERE " . self::audience($uid) . "
               AND NOT EXISTS (SELECT 1 FROM uac.notification_reads r WHERE r.notification_id = n.id AND r.user_id = {$uid})",
            'DB'
        );
    }

    /** Idempotent; only rows inside the caller's audience can be marked. */
    public function markRead(string $id, int $uid): bool
    {
        App\Execute(
            "INSERT INTO uac.notification_reads (notification_id, user_id)
             SELECT n.id, {$uid} FROM uac.notifications n
             WHERE n.id = " . App\QuotedValue($id, DataType::STRING, 'DB') . ' AND ' . self::audience($uid) . "
             ON CONFLICT DO NOTHING",
            'DB'
        );

        return true;
    }

    /** @return int number of notifications newly marked read */
    public function markAllRead(int $uid): int
    {
        $n = App\Execute(
            "INSERT INTO uac.notification_reads (notification_id, user_id)
             SELECT n.id, {$uid} FROM uac.notifications n
             WHERE " . self::audience($uid) . "
             ON CONFLICT DO NOTHING",
            'DB'
        );

        return (int) $n;
    }

    public function isSuperAdmin(int $uid): bool
    {
        return $uid > 0 && trim((string) App\ExecuteScalar(
            "SELECT user_level_id FROM uac.users WHERE user_id = {$uid}",
            'DB'
        )) === '-1';
    }

    // ── Write side: validated send, used by POST /notifications/send ───

    /** @return array{success:bool,message?:string,id?:?string} */
    public function sendValidated(array $in, ?int $senderId = null): array
    {
        $type    = (string) ($in['type'] ?? '');
        $target  = trim((string) ($in['target'] ?? ''));
        $subject = trim((string) ($in['subject'] ?? ''));
        $system  = (string) ($in['from_system'] ?? self::APP_CODE);

        if (!in_array($type, self::TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid type. Must be one of: ' . implode(', ', self::TYPES)];
        }
        if ($type === 'all') {
            $target = 'all';
        } elseif ($target === '') {
            return ['success' => false, 'message' => 'Target is required.'];
        }
        if ($subject === '') {
            return ['success' => false, 'message' => 'Subject is required.'];
        }
        if (!in_array($system, self::allowedSystems(), true)) {
            return ['success' => false, 'message' => 'Invalid system code.'];
        }

        $id = $this->insert($type, $target, $subject, $in['body'] ?? null, $in['link'] ?? null, [
            'from_system' => $system,
            'sender_id'   => $senderId,
            'event_key'   => $in['event_key'] ?? null,
        ]);

        return $id === null
            ? ['success' => false, 'message' => 'Not sent (duplicate event or storage error).']
            : ['success' => true, 'id' => $id];
    }

    /**
     * POST /notifications/test: one real notification to $uid ONLY (type 'user', target = that user — never a broadcast), sent through the
     * same insert() + publish() as every other notification, with a report of both halves:
     *   stored  — it reached uac.notifications (the polling path: it will show in the bell), unread_before / unread_after prove it;
     *   push    — what the EMQX REST API answered (the live path), with a plain-language hint when it did not work.
     * @return array{success:bool,id:?string,stored:bool,topic:string,unread_before:int,unread_after:int,push:array}
     */
    public function sendTest(int $uid): array
    {
        $before = $this->unreadCount($uid);
        $this->lastPush = null;

        $id = $this->insert(
            'user',
            (string) $uid,
            'Test notification',
            'Sent by POST /notifications/test at ' . date('Y-m-d H:i:s') . '. If you can read this, notifications are working.',
            null,
            ['from_system' => self::APP_CODE, 'sender_id' => $uid]
        );

        // not stored: nothing was published either, so there is no push to report on
        if ($id === null) {
            return [
                'success' => false, 'id' => null, 'stored' => false, 'topic' => self::topic('user', (string) $uid),
                'unread_before' => $before, 'unread_after' => $before, 'push' => null,
                'message' => 'The notification could not be stored in uac.notifications (see the PHP error log).',
            ];
        }

        // publish() always runs after a successful insert, so lastPush is set; the fallback only guards a future change
        $push = $this->lastPush ?? ['attempted' => false, 'ok' => false, 'status' => null, 'broker' => null, 'error' => null];
        unset($push['topic']);
        $push['hint'] = self::pushHint($this->lastPush ?? ['attempted' => false, 'status' => null]);

        return [
            'success'       => true,
            'id'            => $id,
            'stored'        => true,
            'topic'         => self::topic('user', (string) $uid),
            'unread_before' => $before,
            'unread_after'  => $this->unreadCount($uid),
            'push'          => $push,
        ];
    }

    // ── Sender library (§5) — static, never throws ───────────────

    public static function send(string $type, string $target, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        try {
            if (!in_array($type, self::TYPES, true) || trim($subject) === '' || ($type !== 'all' && trim($target) === '')) {
                return null;
            }

            return (new self())->insert($type, $type === 'all' ? 'all' : $target, $subject, $body, $link, $opts);
        } catch (\Throwable $e) {
            App\LogError('Notification send failed: ' . $e->getMessage());

            return null;
        }
    }

    public static function toAll(string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('all', 'all', $subject, $body, $link, $opts);
    }

    public static function toSystem(int|string $systemId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('system', (string) $systemId, $subject, $body, $link, $opts);
    }

    public static function toUser(int $userId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return $userId > 0 ? self::send('user', (string) $userId, $subject, $body, $link, $opts) : null;
    }

    public static function toUserLevel(int $levelId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('userlevel', (string) $levelId, $subject, $body, $link, $opts);
    }

    public static function toOffice(int $officeId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('office', (string) $officeId, $subject, $body, $link, $opts);
    }

    public static function toUserGroup(int $supervisorId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('usergroup', (string) $supervisorId, $subject, $body, $link, $opts);
    }

    /**
     * Every ACTIVE user of a Responsibility Center — uac.users.rc_id = $rcId, the same column fims.budget_req.rc_id matches — gets the
     * notification. There is no "rc" audience in the hub (§3), so this is a fan-out: one ordinary 'user' notification per person, which
     * keeps the per-user read tracking and the per-user MQTT topic exactly as they are.
     *   opts['sender_id']  is left out of the recipients (the sender knows what they just did) and recorded as the sender;
     *   opts['event_key']  is made per recipient ("<key>:<user_id>"), so sending the same event twice never notifies anyone twice.
     * Never throws. @return int how many people were notified
     */
    public static function toRc(string $rcId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): int
    {
        $rcId = trim($rcId);
        if ($rcId === '') {
            return 0;
        }

        try {
            $rows = App\ExecuteRows(
                'SELECT user_id FROM uac.users WHERE rc_id = ' . App\QuotedValue($rcId, DataType::STRING, 'DB') . ' AND COALESCE(is_active, TRUE)',
                'DB'
            ) ?: [];

            $skip  = (int) ($opts['sender_id'] ?? 0);
            $key   = isset($opts['event_key']) && $opts['event_key'] !== '' ? (string) $opts['event_key'] : null;
            $count = 0;
            foreach ($rows as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid <= 0 || $uid === $skip) {
                    continue;
                }
                $o = $opts;
                if ($key !== null) {
                    $o['event_key'] = $key . ':' . $uid;
                }
                if (self::toUser($uid, $subject, $body, $link, $o) !== null) {
                    ++$count;
                }
            }

            return $count;
        } catch (\Throwable $e) {
            App\LogError('Notification toRc failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Fan-out to a fims.settings_status row's `next_actors` JSONB — "who acts next on a record that has reached
     * this status" (see that column's own comment). $nextActors is the decoded JSON, e.g.
     * {"users": [], "userLevel": "Budget Officer", "department": "Financial Management Department (FMD)"} — a
     * non-empty "users" list of literal ids is used as-is (it overrides/supplements the level+department
     * lookup, per the column's own doc); otherwise every ACTIVE user holding that user level — uac.users.user_level_id
     * (comma-separated), THE SAME column BudgetReqService::hasUserLevel() / BudgetAllocationService's equivalent
     * read, so "who holds this level" never disagrees between the two — in that department (uac.users.department_id
     * -> uac.departments.department_name) gets it. Same fan-out shape as toRc(): one ordinary 'user' notification
     * per person, opts['sender_id'] excluded, opts['event_key'] made per recipient. $nextActors null/empty/missing
     * both keys -> nobody. Never throws. @return int how many people were notified
     */
    public static function toNextActors(?array $nextActors, string $subject, ?string $body = null, ?string $link = null, array $opts = []): int
    {
        if (!$nextActors) {
            return 0;
        }

        try {
            $ids = array_values(array_filter(array_map('intval', (array) ($nextActors['users'] ?? []))));

            $userLevel  = trim((string) ($nextActors['userLevel'] ?? ''));
            $department = trim((string) ($nextActors['department'] ?? ''));
            if ($userLevel !== '' && $department !== '') {
                $rows = App\ExecuteRows(
                    'SELECT DISTINCT u.user_id
                     FROM uac.users u
                     JOIN uac.departments d ON d.department_id = u.department_id
                     WHERE COALESCE(u.is_active, TRUE)
                       AND LOWER(TRIM(d.department_name)) = LOWER(' . App\QuotedValue($department, DataType::STRING, 'DB') . ')
                       AND EXISTS (
                         SELECT 1 FROM regexp_split_to_table(COALESCE(u.user_level_id, \'\'), \',\') AS part(id)
                         JOIN uac.user_levels ul ON part.id ~ \'^\s*-?\d+\s*$\' AND ul.user_level_id = TRIM(part.id)::int
                         WHERE LOWER(TRIM(ul.name)) = LOWER(' . App\QuotedValue($userLevel, DataType::STRING, 'DB') . ')
                       )',
                    'DB'
                ) ?: [];
                foreach ($rows as $row) {
                    $ids[] = (int) ($row['user_id'] ?? 0);
                }
            }

            $ids = array_values(array_unique(array_filter($ids, fn ($v) => $v > 0)));
            if (!$ids) {
                return 0;
            }

            $skip  = (int) ($opts['sender_id'] ?? 0);
            $key   = isset($opts['event_key']) && $opts['event_key'] !== '' ? (string) $opts['event_key'] : null;
            $count = 0;
            foreach ($ids as $uid) {
                if ($uid === $skip) {
                    continue;
                }
                $o = $opts;
                if ($key !== null) {
                    $o['event_key'] = $key . ':' . $uid;
                }
                if (self::toUser($uid, $subject, $body, $link, $o) !== null) {
                    ++$count;
                }
            }

            return $count;
        } catch (\Throwable $e) {
            App\LogError('Notification toNextActors failed: ' . $e->getMessage());

            return 0;
        }
    }

    // ── Internals ────────────────────────────────────────────────

    /** @return ?string new id, or null when de-duplicated by event_key */
    private function insert(string $type, string $target, string $subject, ?string $body, ?string $link, array $opts): ?string
    {
        $id     = 'ntf_' . bin2hex(random_bytes(12));
        $system = (string) ($opts['from_system'] ?? self::APP_CODE);
        $sender = isset($opts['sender_id']) && (int) $opts['sender_id'] > 0 ? (int) $opts['sender_id'] : null;
        $q      = fn ($v) => $v === null || $v === '' ? 'NULL' : App\QuotedValue((string) $v, DataType::STRING, 'DB');
        $link   = self::safeLink($link);

        $row = App\ExecuteRow(
            'INSERT INTO uac.notifications (id, type, target, subject, body, link, from_system, sender_id, event_key)
             VALUES (' . implode(', ', [
                $q($id), $q($type), $q($target), $q(mb_substr($subject, 0, 200)), $q($body), $q($link),
                $q($system), $sender ?? 'NULL', $q($opts['event_key'] ?? null),
            ]) . ')
             ON CONFLICT (event_key) WHERE event_key IS NOT NULL DO NOTHING
             RETURNING id',
            'DB'
        );
        if (!$row) {
            return null;
        }

        $this->publish([
            'id' => $id, 'type' => $type, 'target' => $target, 'subject' => $subject, 'body' => $body,
            'link' => $link, 'from_system' => $system, 'created_at' => date('c'),
        ]);

        return $id;
    }

    /** Only relative, same-origin links (plan §7/§10). */
    private static function safeLink(?string $link): ?string
    {
        $link = trim((string) $link);

        return ($link !== '' && $link[0] === '/' && !str_starts_with($link, '//')) ? mb_substr($link, 0, 255) : null;
    }

    /** One topic scheme for publisher and browser: app/notifications/{type}/{target}. */
    public static function topic(string $type, string $target): string
    {
        return "app/notifications/{$type}/{$target}";
    }

    /** What the last publish() did (read by sendTest()); null until one has run. */
    private ?array $lastPush = null;

    /**
     * Optional real-time push via EMQX REST. Off when not configured; failures never surface to the caller (they are logged, and
     * recorded in $lastPush so the test route can show them).
     */
    private function publish(array $payload): void
    {
        $result = [
            'attempted' => false, 'ok' => false, 'status' => null, 'broker' => null, 'error' => null,
            'topic'     => self::topic($payload['type'], $payload['target']),
        ];
        try {
            $url    = trim((string) App\Config('EMQX.API_URL'));
            $key    = (string) App\Config('EMQX.API_KEY');
            $secret = (string) App\Config('EMQX.API_SECRET');
            if ($url === '' || $key === '') {
                return;
            }

            $result['attempted'] = true;
            $result['broker']    = self::brokerOf($url);

            $res = (new \GuzzleHttp\Client(['base_uri' => $url, 'timeout' => 3.0, 'http_errors' => false]))->post('/api/v5/publish', [
                'auth' => [$key, $secret],
                'json' => [
                    'topic'   => $result['topic'],
                    'qos'     => 1,
                    'payload' => json_encode($payload),
                    'retain'  => false,
                ],
            ]);

            $result['status'] = $res->getStatusCode();
            $result['ok']     = $result['status'] >= 200 && $result['status'] < 300;
            if (!$result['ok']) {
                $result['error'] = 'HTTP ' . $result['status'];
                App\LogError('Notification publish failed: HTTP ' . $result['status'] . ' ' . mb_substr((string) $res->getBody(), 0, 200));
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            App\LogError('Notification publish failed: ' . $e->getMessage());
        } finally {
            $this->lastPush = $result;
        }
    }

    /** "http://host:18083" — the broker's address without any credentials that may be in the URL. */
    private static function brokerOf(string $url): string
    {
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host'])) {
            return '(unreadable EMQX.API_URL)';
        }

        return ($p['scheme'] ?? 'http') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    /** Plain-language reading of what publish() recorded, for the test route. */
    private static function pushHint(array $p): string
    {
        if (!$p['attempted']) {
            return 'EMQX is not configured (EMQX.API_URL / EMQX.API_KEY are empty), so there is no live push. The notification is stored and will '
                . 'show in the bell on its next poll.';
        }
        if ($p['status'] === null) {
            return "Could not reach the broker's REST API at {$p['broker']} ({$p['error']}). EMQX.API_URL must be the dashboard / API port "
                . '(18083), not the MQTT port (1883), and the broker must be running.';
        }

        return match (true) {
            $p['status'] === 200 => 'The broker accepted it and delivered it to a subscriber — a page this user has open should show it now. If '
                . 'nothing appears, check the browser console for the MQTT connection.',
            $p['status'] === 202 => 'The broker accepted it, but nobody is subscribed to this topic right now: this user has no open page with a live '
                . 'MQTT connection. The notification is stored and will still show on the next poll.',
            in_array($p['status'], [401, 403], true) => 'The broker rejected the API key / secret (HTTP ' . $p['status'] . '). Create an API key in the '
                . 'EMQX dashboard (System → API Keys) and set EMQX.API_KEY / EMQX.API_SECRET.',
            $p['status'] === 404 => 'The broker answered 404 — EMQX.API_URL probably points at the wrong port, or at something that is not EMQX\'s REST API.',
            default => 'The broker answered HTTP ' . $p['status'] . '.',
        };
    }

    private static function allowedSystems(): array
    {
        try {
            $cfg = trim((string) App\Config('NOTIFY.ALLOWED_SYSTEMS'));
        } catch (\Throwable $e) {
            $cfg = '';
        }

        return $cfg !== '' ? array_map('trim', explode(',', $cfg)) : self::DEFAULT_SYSTEMS;
    }

    private static function timeAgo(string $ts): string
    {
        $t = strtotime($ts);
        if (!$t) {
            return '';
        }
        $d = time() - $t;

        return match (true) {
            $d < 60      => 'Just now',
            $d < 3600    => floor($d / 60) . ' minutes ago',
            $d < 86400   => floor($d / 3600) . ' hours ago',
            default      => date('M d, Y', $t),
        };
    }
}
