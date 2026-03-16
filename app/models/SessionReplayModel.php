<?php

class SessionReplayModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getSessionList(int $limit, int $offset): array {
        $stmt = $this->pdo->prepare(
            "SELECT session_id, url, COUNT(*) as event_count,
                    MIN(event_timestamp) as session_start,
                    MAX(event_timestamp) as session_end,
                    MAX(time_on_page) as time_on_page
             FROM activity_data
             GROUP BY session_id, url
             ORDER BY session_start DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countSessions(): int {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM (SELECT DISTINCT session_id, url FROM activity_data) t"
        )->fetchColumn();
    }

    public function getSessionEvents(string $sessionId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM activity_data
             WHERE session_id = :sid
             ORDER BY event_timestamp ASC, id ASC"
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getSessionMeta(string $sessionId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT screen_width, screen_height, viewport_width, viewport_height,
                    user_agent, network_type
             FROM static_data
             WHERE session_id = :sid
             LIMIT 1"
        );
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
