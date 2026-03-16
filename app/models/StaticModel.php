<?php
// Model for static_data. Encapsulates all queries against the table so controllers
// don't need to know SQL or column names.
class StaticModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Returns all rows ordered newest first. Falls back to [] if the table is empty.
    public function getAll(): array {
        return $this->pdo->query("SELECT * FROM static_data ORDER BY id DESC")->fetchAll() ?: [];
    }

    public function getPage(int $page, int $perPage = 25): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare("SELECT * FROM static_data ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countAll(): int {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM static_data")->fetchColumn();
    }

    public function getNetworkTypes(): array {
        return $this->pdo
            ->query("SELECT network_type, COUNT(*) as count FROM static_data GROUP BY network_type ORDER BY count DESC")
            ->fetchAll() ?: [];
    }

    public function getMemoryDistribution(): array {
        return $this->pdo
            ->query("SELECT COALESCE(memory, 0) as memory, COUNT(*) as count FROM static_data GROUP BY memory ORDER BY memory")
            ->fetchAll() ?: [];
    }

    public function getCoresDistribution(): array {
        return $this->pdo
            ->query("SELECT COALESCE(cores, 0) as cores, COUNT(*) as count FROM static_data GROUP BY cores ORDER BY cores")
            ->fetchAll() ?: [];
    }
}
