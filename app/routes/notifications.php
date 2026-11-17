<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\NotificationService;

/**
 * Notification hub API (schema uac) — see _shared/NOTIFICATION-IMPLEMENTATION-PLAN.md §4.
 *
 *   GET  /notifications/recent?limit=   { success, data:[…], unread_count }
 *   GET  /notifications/count           { success, unread_count }
 *   POST /notifications/mark-read       { id }            -> { success }
 *   POST /notifications/mark-all-read                     -> { success, updated }
 *   POST /notifications/send            superadmin only   -> { success, id }
 *   POST /notifications/test            any logged-in user, NOT in production -> { success, id, stored, topic, unread_before, unread_after, push }
 *
 * Every route is authenticated through $jwtMiddleware; the caller is the JWT's user_id (never a parameter).
 * The old /notifications/test route (a hard-coded broadcast) was removed on purpose — plan §10. The one below is deliberately narrower:
 * it takes no parameters, sends ONE notification to the caller only (never anyone else), and is a 404 when Config('ENVIRONMENT') is
 * 'production' (plan §10: no test endpoint reachable in production). It reports both halves of delivery — stored (the polling path) and
 * push (what the EMQX REST API answered, with a hint) — which is what NotificationService::publish() otherwise swallows.
 */
$NotificationService = new NotificationService();

$notifyUser = fn ($request): int => (int) $request->getAttribute('user_id');

$notifyUnauthorized = fn ($response) => $response->withJson(['success' => false, 'message' => 'Not authenticated.'], 401);

$app->get('/notifications/recent', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        $limit = (int) ($request->getQueryParams()['limit'] ?? 10);

        return $response->withJson($NotificationService->recent($uid, $limit));
    } catch (\Throwable $e) {
        LogError('GET /notifications/recent ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not load notifications.'], 500);
    }
})->add($jwtMiddleware);

$app->get('/notifications/count', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        return $response->withJson(['success' => true, 'unread_count' => $NotificationService->unreadCount($uid)]);
    } catch (\Throwable $e) {
        LogError('GET /notifications/count ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not load the count.'], 500);
    }
})->add($jwtMiddleware);

$app->post('/notifications/mark-read', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    $id = trim((string) (($request->getParsedBody() ?: [])['id'] ?? ''));
    if ($id === '') {
        return $response->withJson(['success' => false, 'message' => 'Notification ID required.'], 400);
    }
    try {
        return $response->withJson(['success' => $NotificationService->markRead($id, $uid)]);
    } catch (\Throwable $e) {
        LogError('POST /notifications/mark-read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not mark as read.'], 500);
    }
})->add($jwtMiddleware);

$app->post('/notifications/mark-all-read', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        return $response->withJson(['success' => true, 'updated' => $NotificationService->markAllRead($uid)]);
    } catch (\Throwable $e) {
        LogError('POST /notifications/mark-all-read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not mark all as read.'], 500);
    }
})->add($jwtMiddleware);

// Test: a notification to YOURSELF, to check the pipeline end to end. Call it while logged in (the JWT identifies you):
//   fetch('/api/notifications/test', { method: 'POST', credentials: 'same-origin', headers: { 'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN } })
//     .then(r => r.json()).then(console.log)
// With the bell on screen it should appear (live if the broker push works, otherwise on the next poll). Never sends to anyone else, and is
// switched off — a plain 404 — in production.
$app->post('/notifications/test', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    if (strtolower(trim((string) Config('ENVIRONMENT'))) === 'production') {
        return $response->withJson(['success' => false, 'message' => 'Not found.'], 404);
    }
    try {
        $result = $NotificationService->sendTest($uid);

        return $response->withJson($result, $result['success'] ? 201 : 500);
    } catch (\Throwable $e) {
        LogError('POST /notifications/test ' . $e->getMessage());

        // development only (production never gets here): the real reason helps more than a generic message
        return $response->withJson(['success' => false, 'message' => 'The test notification failed: ' . $e->getMessage()], 500);
    }
})->add($jwtMiddleware);

// Privileged: only a superadmin may broadcast arbitrary notifications. Application code should call
// NotificationService::toUser() / toUserLevel() / … directly instead of going through HTTP.
$app->post('/notifications/send', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        if (!$NotificationService->isSuperAdmin($uid)) {
            return $response->withJson(['success' => false, 'message' => 'Not allowed to send notifications.'], 403);
        }
        $result = $NotificationService->sendValidated($request->getParsedBody() ?: [], $uid);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Throwable $e) {
        LogError('POST /notifications/send ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not send the notification.'], 500);
    }
})->add($jwtMiddleware);
