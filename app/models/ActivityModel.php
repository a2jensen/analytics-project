<?php
// Model for activity_data. Encapsulates all queries against the table so controllers
// don't need to know SQL or column names.
class ActivityModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Returns all rows ordered newest first. Falls back to [] if the table is empty.
    public function getAll(): array {
        return $this->pdo->query("SELECT * FROM activity_data ORDER BY id DESC")->fetchAll() ?: [];
    }

    public function getPage(int $page, int $perPage = 25): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare("SELECT * FROM activity_data ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countAll(): int {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM activity_data")->fetchColumn();
    }

    public function getClickedElements(): array {
        return $this->pdo
            ->query("SELECT tag_name, COUNT(*) as count FROM activity_data WHERE type='click' AND tag_name IS NOT NULL GROUP BY tag_name ORDER BY count DESC LIMIT 10")
            ->fetchAll() ?: [];
    }

    public function getScrollDepthDistribution(): array {
        return $this->pdo
            ->query("SELECT
                CASE
                    WHEN scroll_depth BETWEEN 0 AND 25 THEN '0-25%'
                    WHEN scroll_depth BETWEEN 26 AND 50 THEN '26-50%'
                    WHEN scroll_depth BETWEEN 51 AND 75 THEN '51-75%'
                    ELSE '76-100%'
                END as depth_range,
                COUNT(*) as count
             FROM activity_data
             WHERE type='scroll_final' AND scroll_depth IS NOT NULL
             GROUP BY depth_range
             ORDER BY depth_range")
            ->fetchAll() ?: [];
    }

    // Returns type counts across ALL records for the chart — avoids loading full rows into memory.
    public function getTypeCounts(): array {
        $rows = $this->pdo->query(
            "SELECT type, COUNT(*) AS cnt FROM activity_data GROUP BY type ORDER BY cnt DESC"
        )->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['type'] ?? 'unknown'] = (int) $row['cnt'];
        }
        return $result;
    }
}
