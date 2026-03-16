<?php
// Model for aggregated dashboard data. Each method corresponds to one chart or card group
// on the dashboard view — controllers call these instead of writing inline SQL.
class DashboardModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Returns total row counts for all three tables, keyed by table name.
    // Order matches the display order of the summary cards.
    public function getCounts(): array {
        $counts = [];
        foreach (['static_data', 'performance_data', 'activity_data'] as $table) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
            $counts[$table] = (int) $stmt->fetchColumn();
        }
        return $counts;
    }

    // Returns session counts grouped by network type, descending by count.
    public function getNetworkTypes(): array {
        return $this->pdo
            ->query("SELECT network_type, COUNT(*) as count FROM static_data GROUP BY network_type ORDER BY count DESC")
            ->fetchAll();
    }

    // Returns average timing metrics (ttfb, dom_complete, lcp, total_load_time) across all sessions.
    // PDO returns numeric columns as strings in FETCH_ASSOC mode — callers should cast to float before rounding.
    // Falls back to a zeroed array if the table is empty so the view can safely call round() without crashing.
    public function getPerfAverages(): array {
        $row = $this->pdo
            ->query("SELECT AVG(ttfb) as ttfb, AVG(dom_complete) as dom_complete, AVG(lcp) as lcp, AVG(total_load_time) as total_load_time FROM performance_data")
            ->fetch();
        return $row ?: ['ttfb' => 0, 'dom_complete' => 0, 'lcp' => 0, 'total_load_time' => 0];
    }

    // Returns event counts grouped by type, descending by count.
    public function getEventTypes(): array {
        return $this->pdo
            ->query("SELECT type, COUNT(*) as count FROM activity_data GROUP BY type ORDER BY count DESC")
            ->fetchAll();
    }

    // Returns hourly activity breakdown by event type for the heatmap/stacked bar chart.
    public function getActivityHeatmap(): array {
        return $this->pdo
            ->query("SELECT HOUR(event_timestamp) as hour, type, COUNT(*) as count FROM activity_data GROUP BY hour, type ORDER BY hour, type")
            ->fetchAll();
    }
}
