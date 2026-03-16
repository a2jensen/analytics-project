<?php
// CRUD handler for activity_data. Same structure as static.php but for user interaction events.
require_once 'db.php';

// Routes GET, POST, PUT, DELETE to the appropriate SQL against activity_data.
// php://input is used for POST/PUT for the same reason as in static.php — the body is JSON,
// not form-encoded, so $_POST would be empty.
function handleActivity($method, $id) {
    global $pdo;

    switch ($method) {
        // GET /api/activity or GET /api/activity/{id}
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM activity_data WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['error' => 'not found']);
                } else {
                    echo json_encode($row);
                }
            } else {
                $stmt = $pdo->query("SELECT * FROM activity_data");
                echo json_encode($stmt->fetchAll());
            }
            break;

        // POST /api/activity
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $pdo->prepare("
                INSERT INTO activity_data (
                    session_id, url, type, x, y, mouse_button,
                    key_name, error_message, error_source,
                    idle_duration, idle_end, time_on_page, event_timestamp
                ) VALUES (
                    :session_id, :url, :type, :x, :y, :mouse_button,
                    :key_name, :error_message, :error_source,
                    :idle_duration, :idle_end, :time_on_page, :event_timestamp
                )
            ");
            $stmt->execute([
                ':session_id'    => $data['session_id'] ?? null,
                ':url'           => $data['url'] ?? null,
                ':type'          => $data['type'] ?? null,
                ':x'             => $data['x'] ?? null,
                ':y'             => $data['y'] ?? null,
                ':mouse_button'  => $data['mouse_button'] ?? null,
                ':key_name'      => $data['key_name'] ?? null,
                ':error_message' => $data['error_message'] ?? null,
                ':error_source'  => $data['error_source'] ?? null,
                ':idle_duration' => $data['idle_duration'] ?? null,
                ':idle_end'      => $data['idle_end'] ?? null,
                ':time_on_page'  => $data['time_on_page'] ?? null,
                ':event_timestamp' => $data['event_timestamp'] ?? null
            ]);
            http_response_code(201);
            echo json_encode(['status' => 'created', 'id' => $pdo->lastInsertId()]);
            break;

        // PUT /api/activity/{id}
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $pdo->prepare("
                UPDATE activity_data SET
                    session_id = :session_id,
                    url = :url,
                    type = :type,
                    x = :x,
                    y = :y,
                    mouse_button = :mouse_button,
                    key_name = :key_name,
                    error_message = :error_message,
                    error_source = :error_source,
                    idle_duration = :idle_duration,
                    idle_end = :idle_end,
                    time_on_page = :time_on_page,
                    event_timestamp = :event_timestamp
                WHERE id = :id
            ");
            $stmt->execute([
                ':session_id'      => $data['session_id'] ?? null,
                ':url'             => $data['url'] ?? null,
                ':type'            => $data['type'] ?? null,
                ':x'               => $data['x'] ?? null,
                ':y'               => $data['y'] ?? null,
                ':mouse_button'    => $data['mouse_button'] ?? null,
                ':key_name'        => $data['key_name'] ?? null,
                ':error_message'   => $data['error_message'] ?? null,
                ':error_source'    => $data['error_source'] ?? null,
                ':idle_duration'   => $data['idle_duration'] ?? null,
                ':idle_end'        => $data['idle_end'] ?? null,
                ':time_on_page'    => $data['time_on_page'] ?? null,
                ':event_timestamp' => $data['event_timestamp'] ?? null,
                ':id'              => $id
            ]);
            echo json_encode(['status' => 'updated']);
            break;

        // DELETE /api/activity/{id}
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM activity_data WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['status' => 'deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);
    }
}
?>