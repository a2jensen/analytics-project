<?php
// Model for performance_data. Encapsulates all queries against the table so controllers
// don't need to know SQL or column names.
class PerformanceModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Returns all rows ordered newest first. Falls back to [] if the table is empty.
    public function getAll(): array {
        return $this->pdo->query("SELECT * FROM performance_data ORDER BY id DESC")->fetchAll() ?: [];
    }

    public function getPage(int $page, int $perPage = 25): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare("SELECT * FROM performance_data ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countAll(): int {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM performance_data")->fetchColumn();
    }

    public function getWebVitalsAvg(): array {
        $row = $this->pdo
            ->query("SELECT ROUND(AVG(lcp),1) as lcp, ROUND(AVG(cls),4) as cls, ROUND(AVG(inp),1) as inp FROM performance_data")
            ->fetch();
        return $row ?: ['lcp' => 0, 'cls' => 0, 'inp' => 0];
    }

    public function getNetworkTimingAvg(): array {
        $row = $this->pdo
            ->query("SELECT ROUND(AVG(dns_lookup),1) as dns, ROUND(AVG(tcp_connect),1) as tcp, ROUND(AVG(tls_handshake),1) as tls, ROUND(AVG(ttfb),1) as ttfb, ROUND(AVG(download),1) as download FROM performance_data")
            ->fetch();
        return $row ?: ['dns' => 0, 'tcp' => 0, 'tls' => 0, 'ttfb' => 0, 'download' => 0];
    }

    // Returns the N most recent rows for the chart — only the columns the chart needs.
    public function getRecentForChart(int $limit = 10): array {
        $stmt = $this->pdo->prepare(
            "SELECT session_id, ttfb, lcp, dom_complete
             FROM performance_data ORDER BY id DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        // Reverse so the chart plots oldest → newest (left to right).
        return array_reverse($stmt->fetchAll() ?: []);
    }
}
