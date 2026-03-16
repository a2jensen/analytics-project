<?php

class SessionReplayController {
    public static function index(): void {
        Auth::require();
        if (!Auth::canAccessSection('activity')) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model      = new SessionReplayModel($pdo);
            $total      = $model->countSessions();
            $totalPages = max(1, (int) ceil($total / 25));
            $page       = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
            $sessions   = $model->getSessionList(25, ($page - 1) * 25);
            $baseUrl    = '/replay';
            require __DIR__ . '/../views/replay/index.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    public static function show(): void {
        Auth::require();
        if (!Auth::canAccessSection('activity')) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }

        $sessionId = trim($_GET['session_id'] ?? '');
        if (empty($sessionId)) {
            header('Location: /replay');
            exit;
        }

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model  = new SessionReplayModel($pdo);
            $events = $model->getSessionEvents($sessionId);

            if (empty($events)) {
                http_response_code(404);
                require __DIR__ . '/../views/errors/404.php';
                return;
            }

            $meta = $model->getSessionMeta($sessionId);
            require __DIR__ . '/../views/replay/show.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }
}
