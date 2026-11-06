<?php
namespace PHPMaker2024\ITBS_STANDARD_APP;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class NotificationManager {
    private static ?Client $httpClient = null;
    private static $retryAttempts = 3;

    /**
     * Initialize HTTP client
     */
    private static function getHttpClient(): Client {
        if (self::$httpClient === null) {
            self::$httpClient = new Client([
                'base_uri' => Config("EMQX.API_URL"),
                'timeout'  => 5.0,
                'verify' => false // For development
            ]);
        }
        return self::$httpClient;
    }

    /**
     * Send message via EMQX REST API
     */
    private static function sendViaEmqx($topic, $payload): bool {
        try {
            $client = self::getHttpClient();
            
            Log("EMQX API Request:", [
                'topic' => $topic,
                'payload' => $payload
            ]);

            $response = $client->post('/api/v5/publish', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(
                        Config("EMQX.API_KEY") . ':' . Config("EMQX.API_SECRET")
                    ),
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'topic' => $topic,
                    'qos' => 1,
                    'payload' => json_encode($payload),
                    'retain' => false
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            Log("EMQX API Response: " . json_encode($result));

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            Log("EMQX API Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification with retries
     */
    private static function sendMqttNotification($id, $timestamp, $type, $target, $subject, $body, $link, $fromSystem): bool {
        $payload = [
            "id" => $id,
            "timestamp" => $timestamp,
            "type" => $type,
            "target" => $target,
            "subject" => $subject,
            "body" => $body,
            "link" => $link,
            "from_system" => $fromSystem,
            "is_read" => false
        ];

        $topic = "app/notifications/{$type}/{$target}";
        
        Log("Publishing notification:", [
            'topic' => $topic,
            'id' => $id,
            'type' => $type
        ]);

        for ($attempt = 1; $attempt <= self::$retryAttempts; $attempt++) {
            try {
                if ($attempt > 1) {
                    Log("Retry attempt {$attempt} of " . self::$retryAttempts);
                    sleep(1);
                }

                if (self::sendViaEmqx($topic, $payload)) {
                    Log("Notification published successfully");
                    return true;
                }
            } catch (\Exception $e) {
                Log("Error on attempt {$attempt}: " . $e->getMessage());
                if ($attempt === self::$retryAttempts) {
                    Log("All retry attempts failed");
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Send notification
     */
    public static function send($type, $target, $subject, $body, $link = null, $fromSystem = null, $userId = null): bool {
        try {
            // Get current system name if not provided
            if ($fromSystem === null) {
                $fromSystem = basename(dirname($_SERVER['PHP_SELF']));
            }

            $id = uniqid("app_", true);
            $timestamp = date("c");
            
            Log("Sending notification - Type: $type, Target: $target, From: $fromSystem");

            try {
                $sql = sprintf(
                    "INSERT INTO notifications (id, timestamp, type, target, user_id, subject, body, link, from_system, is_read, created_at) 
                    VALUES ('%s', '%s', '%s', '%s', %s, '%s', '%s', %s, '%s', false, CURRENT_TIMESTAMP)",
                    addslashes($id),
                    addslashes($timestamp),
                    addslashes($type),
                    addslashes($target),
                    $userId ? intval($userId) : "NULL",
                    addslashes($subject),
                    addslashes($body),
                    $link ? "'" . addslashes($link) . "'" : "NULL",
                    addslashes($fromSystem)
                );
                
                Execute($sql, "DB");
                Log("Database insert successful");

                $emqxResult = self::sendMqttNotification(
                    $id, $timestamp, $type, $target, 
                    $subject, $body, $link, $fromSystem
                );

                Log("EMQX Result: " . ($emqxResult ? "Success" : "Failed"));

                return true;
            } catch (\Exception $e) {
                Log("Database Error: " . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            Log("General Error: " . $e->getMessage());
            return false;
        }
    }

    // Helper methods for different notification types
    public static function sendSystem($subject, $body, $link = null, $fromSystem = null): bool {
        return self::send("system", "all", $subject, $body, $link, $fromSystem);
    }

    public static function sendToUser($userId, $subject, $body, $link = null, $fromSystem = null): bool {
        return self::send("personal", $userId, $subject, $body, $link, $fromSystem, $userId);
    }

    public static function sendToUserLevel($userLevelId, $subject, $body, $link = null, $fromSystem = null): bool {
        return self::send("userLevel", $userLevelId, $subject, $body, $link, $fromSystem);
    }

    /**
     * Get user notifications
     */
    public static function getUserNotifications($userId, $limit = 10): array {
        $sql = sprintf(
            "SELECT n.*, 
                CASE 
                    WHEN n.type = 'system' THEN 'System'
                    WHEN n.type = 'personal' THEN n.from_system
                    WHEN n.type = 'userLevel' THEN ul.name
                END as source,
                CASE 
                    WHEN n.timestamp > CURRENT_TIMESTAMP - INTERVAL '1 minute' THEN 'Just now'
                    WHEN n.timestamp > CURRENT_TIMESTAMP - INTERVAL '1 hour' THEN 
                        EXTRACT(MINUTE FROM CURRENT_TIMESTAMP - n.timestamp)::text || ' minutes ago'
                    WHEN n.timestamp > CURRENT_TIMESTAMP - INTERVAL '1 day' THEN 
                        EXTRACT(HOUR FROM CURRENT_TIMESTAMP - n.timestamp)::text || ' hours ago'
                    ELSE TO_CHAR(n.timestamp, 'Mon DD, YYYY')
                END as time_ago
            FROM notifications n
            LEFT JOIN user_levels ul ON n.target = CAST(ul.user_level_id as TEXT)
            WHERE (n.type = 'personal' AND n.target = '%s')
               OR (n.type = 'userLevel' AND n.target IN (
                   SELECT CAST(user_level_id AS VARCHAR) 
                   FROM user_level_assignments 
                   WHERE user_id = %d
               ))
               OR (n.type = 'system' AND n.target = 'all')
            ORDER BY n.timestamp DESC 
            LIMIT %d",
            addslashes($userId),
            intval($userId),
            intval($limit)
        );
                
        return ExecuteRowsAssociative($sql, "DB") ?? [];
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId, $userId): bool {
        $sql = sprintf(
            "UPDATE notifications 
            SET is_read = true 
            WHERE id = '%s' AND (
                type = 'system' 
                OR (type = 'personal' AND target = '%s')
                OR (type = 'userLevel' AND target IN (
                    SELECT CAST(user_level_id AS VARCHAR)
                    FROM user_level_assignments 
                    WHERE user_id = %d
                ))
            )",
            addslashes($notificationId),
            addslashes($userId),
            intval($userId)
        );
        return Execute($sql, "DB") !== false;
    }

    /**
     * Get unread notification count for user
     */
    public static function getUnreadCount($userId): int {
        $sql = sprintf(
            "SELECT COUNT(*) FROM notifications 
            WHERE is_read = false AND (
                (type = 'personal' AND target = '%s')
                OR (type = 'userLevel' AND target IN (
                    SELECT CAST(user_level_id AS VARCHAR)
                    FROM user_level_assignments 
                    WHERE user_id = %d
                ))
                OR (type = 'system' AND target = 'all')
            )",
            addslashes($userId),
            intval($userId)
        );
                
        return (int)ExecuteScalar($sql, "DB");
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead($userId): bool {
        $sql = sprintf(
            "UPDATE notifications 
            SET is_read = true 
            WHERE is_read = false AND (
                (type = 'personal' AND target = '%s')
                OR (type = 'userLevel' AND target IN (
                    SELECT CAST(user_level_id AS VARCHAR)
                    FROM user_level_assignments 
                    WHERE user_id = %d
                ))
                OR (type = 'system' AND target = 'all')
            )",
            addslashes($userId),
            intval($userId)
        );
        return Execute($sql, "DB") !== false;
    }
}
