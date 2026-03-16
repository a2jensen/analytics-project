<?php

class RateLimiter {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function isRateLimited(string $ip, string $endpoint, int $maxAttempts, int $windowSeconds): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rate_limits
             WHERE ip_address = ? AND endpoint = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$ip, $endpoint, $windowSeconds]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;
    }

    public function recordAttempt(string $ip, string $endpoint): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, ?)"
        );
        $stmt->execute([$ip, $endpoint]);
    }

    public function clearAttempts(string $ip, string $endpoint): void {
        $stmt = $this->pdo->prepare(
            "DELETE FROM rate_limits WHERE ip_address = ? AND endpoint = ?"
        );
        $stmt->execute([$ip, $endpoint]);
    }
}
