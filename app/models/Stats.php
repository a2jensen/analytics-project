<?php
// i need to req once db.php here because this model is responsible for fetching data from the database for the reports views.
// just have req once instead of repeating?
class StatsModel {
    // This class is currently empty because we have no complex data manipulation or business logic for stats.
    // In a more complex application, this would contain methods for processing or aggregating stats data before it's displayed.
    // For our simple use case, all data fetching and display logic is handled in the ReportsController and views.
    public static function getAll(): array {
        require_once __DIR__ . '/../../api/db.php';
        return $pdo->query("SELECT * FROM static_data ORDER BY id DESC")->fetchAll();
    }
    
    public static function getPerformanceData(): array {
        require_once __DIR__ . '/../../api/db.php';
        return $pdo->query("SELECT * FROM performance_data ORDER BY id DESC")->fetchAll();
    }

    public static function getActivityData(): array {
        require_once __DIR__ . '/../../api/db.php';
        return $pdo->query("SELECT * FROM activity_data ORDER BY id DESC")->fetchAll();
    }

    public static function getStaticData(): array {
        require_once __DIR__ . '/../../api/db.php';
        return $pdo->query("SELECT * FROM static_data ORDER BY id DESC")->fetchAll();
    }
}