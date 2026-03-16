<?php
// Model for the users table. Handles all credential lookups so AuthController
// never touches SQL directly.
class UserModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Returns the matching user row, or null if not found.
    // LIMIT 1 is a safety guard — username has a UNIQUE constraint but this makes the
    // intent explicit and prevents fetching multiple rows if the constraint is ever relaxed.
    public function findByUsername(string $username): ?array {
        if (empty($username)) {
            return null; // early return for empty username to avoid unnecessary DB query
        }
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Returns a flat array of section names the user is permitted to access,
    // e.g. ['performance', 'activity']. Returns [] for non-analysts or users with no sections assigned.
    // Called at login time — result is cached in $_SESSION['sections'] for the duration of the session.
    public function getSectionsForUser(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT section FROM user_sections WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    // Returns all users with their assigned sections as a comma-separated string for display.
    // GROUP_CONCAT avoids N+1 queries when rendering the user list table.
    public function getAllWithSections(): array {
        return $this->pdo->query(
            "SELECT u.id, u.username, u.role, u.created_at,
                    GROUP_CONCAT(s.section ORDER BY s.section SEPARATOR ', ') AS sections
             FROM users u
             LEFT JOIN user_sections s ON s.user_id = u.id
             GROUP BY u.id
             ORDER BY u.id ASC"
        )->fetchAll() ?: [];
    }

    public function getPageWithSections(int $page, int $perPage = 25): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "SELECT u.id, u.username, u.role, u.created_at,
                    GROUP_CONCAT(s.section ORDER BY s.section SEPARATOR ', ') AS sections
             FROM users u
             LEFT JOIN user_sections s ON s.user_id = u.id
             GROUP BY u.id
             ORDER BY u.id ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countAll(): int {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    // Returns a single user row by ID, or null if not found.
    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // Inserts a new user and returns the new row's ID.
    public function create(string $username, string $passwordHash, string $role): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password_hash, role) VALUES (:username, :hash, :role)"
        );
        $stmt->execute([':username' => $username, ':hash' => $passwordHash, ':role' => $role]);
        return (int) $this->pdo->lastInsertId();
    }

    // Updates role and optionally password. Pass null for $passwordHash to leave it unchanged.
    public function update(int $id, string $role, ?string $passwordHash): void {
        // DEBUG
        error_log("DEBUG UserModel::update() called with: " . print_r([
            'id'           => $id,
            'role'         => $role,
            'passwordHash' => $passwordHash ?? '(null — not updating password)',
        ], true));
        // END DEBUG

        if ($passwordHash !== null) {
            $stmt = $this->pdo->prepare(
                "UPDATE users SET role = :role, password_hash = :hash WHERE id = :id"
            );
            $stmt->execute([':role' => $role, ':hash' => $passwordHash, ':id' => $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->execute([':role' => $role, ':id' => $id]);
        }

        // DEBUG
        error_log("DEBUG UserModel::update() rows affected: " . $stmt->rowCount());
        // END DEBUG
    }

    // Replaces all section assignments for a user.
    // Deletes existing rows first so this can be called for both create and update.
    // If role is not analyst, passing an empty array effectively clears all sections.
    public function replaceSections(int $userId, array $sections): void {
        $this->pdo->prepare("DELETE FROM user_sections WHERE user_id = :user_id")
                  ->execute([':user_id' => $userId]);

        if (!empty($sections)) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO user_sections (user_id, section) VALUES (:user_id, :section)"
            );
            foreach ($sections as $section) {
                $stmt->execute([':user_id' => $userId, ':section' => $section]);
            }
        }
    }

    // Deletes a user by ID. Cascades to user_sections via the FK constraint.
    public function deleteById(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}