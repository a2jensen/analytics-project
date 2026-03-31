<?php
// Handles saved reports — accessible to all roles for viewing, analysts and super_admins for saving.
class ReportController {
    public static function index(): void {
        Auth::require();
        try {
            require_once __DIR__ . '/../../api/db.php';
            $model               = new ReportModel($pdo);
            $total               = $model->countAll();
            $totalPages          = max(1, (int) ceil($total / 25));
            $page                = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
            $reports             = $model->getPage($page);
            $baseUrl             = '/reports/saved';
            require __DIR__ . '/../views/reports/saved.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    // Shows the report generation form for a given section.
    // Only analysts (with access to that section) and super_admins may generate reports.
    public static function generate(string $section): void {
        Auth::requireRole('super_admin', 'analyst');
        if (!Auth::canAccessSection($section)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        $error = null;

        // Pre-populate commentary textarea with auto-generated insights
        $autoCommentary = '';
        try {
            require_once __DIR__ . '/../../api/db.php';
            if (method_exists('CommentaryGenerator', $section)) {
                $autoCommentary = CommentaryGenerator::$section($pdo);
            }
        } catch (Exception $e) {
            // Non-critical — form still works with empty commentary
        }

        require __DIR__ . '/../views/reports/generate.php';
    }

    // Handles the POST from the generation form.
    // Fetches the filtered records, snapshots them as JSON, and saves the report.
    public static function store(): void {
        Auth::requireRole('super_admin', 'analyst');

        $title     = trim($_POST['title']      ?? '');
        $section   = trim($_POST['section']    ?? '');
        $commentary = trim($_POST['commentary'] ?? '');
        $chartType = trim($_POST['chart_type'] ?? 'bar');
        $fromId    = (int)($_POST['id_from']   ?? 0);
        $toId      = (int)($_POST['id_to']     ?? 0);

        $validSections  = ['static', 'performance', 'activity'];
        $validChartTypes = ['bar', 'line', 'pie', 'doughnut'];

        if (
            empty($title) ||
            !in_array($section, $validSections, true) ||
            !in_array($chartType, $validChartTypes, true) ||
            $fromId <= 0 || $toId <= 0 || $fromId > $toId
        ) {
            $error = 'Please fill in all fields with valid values.';
            require __DIR__ . '/../views/reports/generate.php';
            return;
        }

        if (!Auth::canAccessSection($section)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model    = new ReportModel($pdo);
            $snapshot = $model->fetchSnapshot($section, $fromId, $toId);

            if (empty($snapshot)) {
                $error = 'No records found in that ID range. Please adjust the range and try again.';
                require __DIR__ . '/../views/reports/generate.php';
                return;
            }

            $userRow = (new UserModel($pdo))->findByUsername($_SESSION['user']);
            $model->save($title, $section, $commentary, $chartType, $snapshot, (int)$userRow['id']);
            header('Location: /reports/saved');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    // Displays a single saved report — frozen table + Chart.js chart + commentary.
    public static function view(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);

        try {
            require_once __DIR__ . '/../../api/db.php';
            $report = (new ReportModel($pdo))->findById($id);

            if (!$report) {
                http_response_code(404);
                require __DIR__ . '/../views/errors/404.php';
                return;
            }

            require __DIR__ . '/../views/reports/view.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }
}
