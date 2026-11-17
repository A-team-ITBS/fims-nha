<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\FtlaService;

// Fund Transfer Letter of Advice (FTLA) — backs app/pages/ftla-page.php,
// app/pages/form/ftla-form.php and app/pages/readone/ftla-readone.php.
// Literal routes (bank-accounts, kpis, list, …) are registered BEFORE the
// generic /ftla/{id} — Slim/FastRoute tries alternatives in registration
// order, so {id} would otherwise swallow them (e.g. "list" as id="list").
$FtlaService = new FtlaService();

/** The verified user's id from the JWT middleware, or 0. */
$ftlaUserId = static function ($request): int {
    $userId = $request->getAttribute('user_id');

    return $userId !== null && $userId !== '' ? (int) $userId : 0;
};

$app->get('/ftla/transfer-types', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getTransferTypes();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/transfer-types ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/bank-accounts', function ($request, $response, $args) use ($FtlaService) {
    try {
        $all    = filter_var($request->getQueryParams()['all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $result = $FtlaService->getBankAccounts($all);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/bank-accounts ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/kpis', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getKpis();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/kpis ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/list', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getList($request->getQueryParams());

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/default-signatories', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getDefaultSignatories();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/default-signatories ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/workflow-meta', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->fetchWorkflowMeta();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/workflow-meta ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/workflow-steps', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->fetchWorkflowSteps();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/workflow-steps ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Attachment upload — BEFORE /{id} to avoid route conflict.
$app->post('/ftla/attachment', function ($request, $response, $args) use ($FtlaService, $ftlaUserId) {
    try {
        $result = $FtlaService->uploadAttachment(
            $request->getParsedBody() ?: [],
            $request->getUploadedFiles(),
            $ftlaUserId($request)
        );

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /ftla/attachment ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Create. body.status: DRAFT (default on "Save Draft") | omitted (= "Submit")
$app->post('/ftla/add', function ($request, $response, $args) use ($FtlaService, $ftlaUserId) {
    try {
        $result = $FtlaService->create($request->getParsedBody() ?: [], $ftlaUserId($request));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 201 : 400));
    } catch (\Exception $e) {
        LogError('POST /ftla/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Single record.
$app->get('/ftla/{id}', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getRecord((int) $args['id']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 500));
    } catch (\Exception $e) {
        LogError('GET /ftla/{id} ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update — full edit (form modal) or a bare {status[, remarks]} advance (list page / status modal).
$app->post('/ftla/update/{id}', function ($request, $response, $args) use ($FtlaService, $ftlaUserId) {
    try {
        $body   = $request->getParsedBody() ?: [];
        $result = $FtlaService->update($args['id'], $body, $ftlaUserId($request), isset($body['remarks']) ? (string) $body['remarks'] : null);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /ftla/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/{id}/timeline', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getTimeline((int) $args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/{id}/timeline ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/{id}/history', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getHistory((int) $args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/{id}/history ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/ftla/{id}/utilization', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->getDvUtilization((int) $args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /ftla/{id}/utilization ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/ftla/{id}/print-docx', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->printDocx((int) $args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : ($result['httpCode'] ?? 501));
    } catch (\Exception $e) {
        LogError('POST /ftla/{id}/print-docx ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/ftla/{id}/print-pdf', function ($request, $response, $args) use ($FtlaService) {
    try {
        $result = $FtlaService->printPdf((int) $args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : ($result['httpCode'] ?? 501));
    } catch (\Exception $e) {
        LogError('POST /ftla/{id}/print-pdf ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
