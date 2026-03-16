<?php
// Model for the reports table. Handles saved/published reports that all roles can view.
class ReportModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Returns all saved reports joined with the creator's username, newest first.
    public function getAll(): array {
        return $this->pdo->query(
            "SELECT r.id, r.title, r.section, r.chart_type, r.created_at,
                    u.username AS created_by_username
             FROM reports r
             JOIN users u ON u.id = r.created_by
             ORDER BY r.created_at DESC"
        )->fetchAll() ?: [];
    }

    public function getPage(int $page, int $perPage = 25): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "SELECT r.id, r.title, r.section, r.chart_type, r.created_at,
                    u.username AS created_by_username
             FROM reports r
             JOIN users u ON u.id = r.created_by
             ORDER BY r.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countAll(): int {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
    }

    // Returns a single report by ID with snapshot_data decoded back to an array.
    // Returns null if not found.
    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username AS created_by_username
             FROM reports r
             JOIN users u ON u.id = r.created_by
             WHERE r.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['snapshot_data'] = json_decode($row['snapshot_data'], true) ?: [];
        return $row;
    }

    // Saves a new report. snapshot_data is encoded as JSON — the frozen record set.
    public function save(
        string $title,
        string $section,
        string $commentary,
        string $chartType,
        array  $snapshotData,
        int    $createdBy
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reports (title, section, commentary, chart_type, snapshot_data, created_by)
             VALUES (:title, :section, :commentary, :chart_type, :snapshot_data, :created_by)"
        );
        $stmt->execute([
            ':title'         => $title,
            ':section'       => $section,
            ':commentary'    => $commentary,
            ':chart_type'    => $chartType,
            ':snapshot_data' => json_encode($snapshotData),
            ':created_by'    => $createdBy,
        ]);
    }

    // Fetches records from the given section table within an ID range for snapshotting.
    // Used by ReportController::store() before saving.
    public function fetchSnapshot(string $section, int $fromId, int $toId): array {
        $table = match($section) {
            'static'      => 'static_data',
            'performance' => 'performance_data',
            'activity'    => 'activity_data',
            default       => throw new InvalidArgumentException("Invalid section: $section"),
        };
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `$table` WHERE id BETWEEN :from_id AND :to_id ORDER BY id ASC"
        );
        $stmt->execute([':from_id' => $fromId, ':to_id' => $toId]);
        return $stmt->fetchAll() ?: [];
    }
}
