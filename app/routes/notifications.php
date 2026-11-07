<?php
// __BASE__/app/routes/notifications.php
namespace PHPMaker2024\FIMS;

// API Routes
$app->get("/notifications/recent", function ($request, $response) {
    $userId = CurrentUserID();
    $notifications = NotificationManager::getUserNotifications($userId);
    
    return $response->withJson([
        'success' => true,
        'data' => $notifications,
        'unread_count' => NotificationManager::getUnreadCount($userId)
    ]);
});

$app->post("/notifications/mark-read", function ($request, $response) {
    $data = $request->getParsedBody();
    $notificationId = $data['id'] ?? null;
    $userId = CurrentUserID();
    
    if (!$notificationId) {
        return $response->withStatus(400)->withJson([
            'success' => false,
            'message' => 'Notification ID required'
        ]);
    }

    $success = NotificationManager::markAsRead($notificationId, $userId);
    
    return $response->withJson([
        'success' => $success
    ]);
});

$app->get("/notifications/count", function ($request, $response) {
    $userId = CurrentUserID();
    $count = NotificationManager::getUnreadCount($userId);
    
    return $response->withJson([
        'success' => true,
        'count' => $count
    ]);
});

$app->post("/notifications/mark-all-read", function ($request, $response) {
    $userId = CurrentUserID();
    $success = NotificationManager::markAllAsRead($userId);
    
    return $response->withJson([
        'success' => $success
    ]);
});

$app->get("/notifications/test", function ($request, $response) {
    $userId = CurrentUserID();
    $userLevel = CurrentUserLevel();
    
    try {
        // Test System Notification
        $systemResult = NotificationManager::sendSystem(
            "🔧 Test System Notification",
            "This is a test system-wide notification sent at " . date('Y-m-d H:i:s'),
            "/test-system",
            "__BASE__"
        );

        // Test Personal Notification
        $personalResult = NotificationManager::sendToUser(
            $userId,
            "👤 Test Personal Notification",
            "This is a test personal notification for user #" . $userId,
            "/test-personal",
            "__BASE__"
        );

        // Test User Level Notification
        $userLevelResult = NotificationManager::sendToUserLevel(
            $userLevel,
            "👥 Test User Level Notification",
            "This is a test notification for user level #" . $userLevel,
            "/test-user-level",
            "__BASE__"
        );

        return $response->withJson([
            'success' => true,
            'results' => [
                'system' => $systemResult,
                'personal' => $personalResult,
                'userLevel' => $userLevelResult
            ],
            'user_info' => [
                'user_id' => $userId,
                'user_level' => $userLevel
            ]
        ]);

    } catch (\Exception $e) {
        return $response->withStatus(500)->withJson([
            'success' => false,
            'message' => 'Error sending test notifications',
            'error' => $e->getMessage()
        ]);
    }
});

/**
 * Notification API Routes
 * 
 * Example payloads for different notification types:
 * 
 * 1. System-wide notification:
 * POST /notifications/send
 * {
 *     "type": "system",
 *     "subject": "System Maintenance",
 *     "body": "The system will be down for maintenance on Sunday at 2 AM.",
 *     "link": "/maintenance-schedule",
 *     "from_system": "__BASE__"
 * }
 * 
 * 2. Personal notification:
 * {
 *     "type": "personal",
 *     "target": "123",  // user_id
 *     "subject": "Document Approved",
 *     "body": "Your document #ABC123 has been approved.",
 *     "link": "/documents/ABC123",
 *     "from_system": "__BASE__"
 * }
 * 
 * 3. User Level notification:
 * {
 *     "type": "userLevel",
 *     "target": "1000",  // user_level_id
 *     "subject": "New Feature Available",
 *     "body": "Archive search feature is now available for administrators.",
 *     "link": "/features/archive-search",
 *     "from_system": "__BASE__"
 * }
 * 
 * 4. Emergency notification:
 * {
 *     "type": "system",
 *     "subject": "⚠️ Emergency Alert",
 *     "body": "Emergency maintenance required. System access may be limited.",
 *     "link": "/emergency-status",
 *     "from_system": "__BASE__"
 * }
 * 
 * 5. Feature announcement:
 * {
 *     "type": "userLevel",
 *     "target": "2000",  // Librarian user level
 *     "subject": "📚 New Library Features",
 *     "body": "New cataloging system is now available.",
 *     "link": "/library/features",
 *     "from_system": "__BASE__"
 * }
 */

 $app->post("/notifications/send", function ($request, $response) {
    try {
        $data = $request->getParsedBody();
        
        // Validate required fields
        $requiredFields = ['type', 'subject', 'body'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $response->withStatus(400)->withJson([
                    'success' => false,
                    'message' => "Missing required field: {$field}"
                ]);
            }
        }

        // Validate notification type
        $validTypes = ['system', 'personal', 'userLevel'];
        if (!in_array($data['type'], $validTypes)) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => "Invalid notification type. Must be one of: " . implode(', ', $validTypes)
            ]);
        }

        // If not system notification, target is required
        if ($data['type'] !== 'system' && empty($data['target'])) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => "Target is required for {$data['type']} notifications"
            ]);
        }

        // Send notification based on type
        $result = false;
        switch ($data['type']) {
            case 'system':
                $result = NotificationManager::sendSystem(
                    $data['subject'],
                    $data['body'],
                    $data['link'] ?? null,
                    $data['from_system'] ?? null
                );
                break;

            case 'personal':
                $result = NotificationManager::sendToUser(
                    $data['target'],
                    $data['subject'],
                    $data['body'],
                    $data['link'] ?? null,
                    $data['from_system'] ?? null
                );
                break;

            case 'userLevel':
                $result = NotificationManager::sendToUserLevel(
                    $data['target'],
                    $data['subject'],
                    $data['body'],
                    $data['link'] ?? null,
                    $data['from_system'] ?? null
                );
                break;
        }

        return $response->withJson([
            'success' => $result,
            'message' => $result ? 'Notification sent successfully' : 'Failed to send notification'
        ]);

    } catch (\Exception $e) {
        return $response->withStatus(500)->withJson([
            'success' => false,
            'message' => 'Error sending notification',
            'error' => $e->getMessage()
        ]);
    }
}); 