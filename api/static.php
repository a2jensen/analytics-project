<?php
// CRUD handler for static_data. Each case corresponds to an HTTP method on the /api/static resource.
require_once 'db.php';

// Routes GET, POST, PUT, DELETE to the appropriate SQL against static_data.
// POST/PUT read the request body via php://input because the body is JSON (not form-encoded),
// so $_POST would be empty.
function handleStatic($method, $id) {
    global $pdo;

    switch ($method) {
        // GET /api/static or GET /api/static/{id}
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM static_data WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['error' => 'not found']);
                } else {
                    echo json_encode($row);
                }
            } else {
                $stmt = $pdo->query("SELECT * FROM static_data");
                echo json_encode($stmt->fetchAll());
            }
            break;

        // POST /api/static
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $pdo->prepare("
                INSERT INTO static_data (
                    session_id, url, user_agent, language, cookies_enabled,
                    js_enabled, images_enabled, css_enabled,
                    screen_width, screen_height, viewport_width, viewport_height,
                    pixel_ratio, cores, memory, network_type, color_scheme, timezone
                ) VALUES (
                    :session_id, :url, :user_agent, :language, :cookies_enabled,
                    :js_enabled, :images_enabled, :css_enabled,
                    :screen_width, :screen_height, :viewport_width, :viewport_height,
                    :pixel_ratio, :cores, :memory, :network_type, :color_scheme, :timezone
                )
            ");
            $stmt->execute([
                ':session_id'      => $data['session_id'] ?? null,
                ':url'             => $data['url'] ?? null,
                ':user_agent'      => $data['user_agent'] ?? null,
                ':language'        => $data['language'] ?? null,
                ':cookies_enabled' => $data['cookies_enabled'] ?? null,
                ':js_enabled'      => $data['js_enabled'] ?? null,
                ':images_enabled'  => $data['images_enabled'] ?? null,
                ':css_enabled'     => $data['css_enabled'] ?? null,
                ':screen_width'    => $data['screen_width'] ?? null,
                ':screen_height'   => $data['screen_height'] ?? null,
                ':viewport_width'  => $data['viewport_width'] ?? null,
                ':viewport_height' => $data['viewport_height'] ?? null,
                ':pixel_ratio'     => $data['pixel_ratio'] ?? null,
                ':cores'           => $data['cores'] ?? null,
                ':memory'          => $data['memory'] ?? null,
                ':network_type'    => $data['network_type'] ?? null,
                ':color_scheme'    => $data['color_scheme'] ?? null,
                ':timezone'        => $data['timezone'] ?? null
            ]);
            http_response_code(201);
            echo json_encode(['status' => 'created', 'id' => $pdo->lastInsertId()]);
            break;

        // PUT /api/static/{id}
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $pdo->prepare("
                UPDATE static_data SET
                    session_id = :session_id,
                    url = :url,
                    user_agent = :user_agent,
                    language = :language,
                    cookies_enabled = :cookies_enabled,
                    js_enabled = :js_enabled,
                    images_enabled = :images_enabled,
                    css_enabled = :css_enabled,
                    screen_width = :screen_width,
                    screen_height = :screen_height,
                    viewport_width = :viewport_width,
                    viewport_height = :viewport_height,
                    pixel_ratio = :pixel_ratio,
                    cores = :cores,
                    memory = :memory,
                    network_type = :network_type,
                    color_scheme = :color_scheme,
                    timezone = :timezone
                WHERE id = :id
            ");
            $stmt->execute([
                ':session_id'      => $data['session_id'] ?? null,
                ':url'             => $data['url'] ?? null,
                ':user_agent'      => $data['user_agent'] ?? null,
                ':language'        => $data['language'] ?? null,
                ':cookies_enabled' => $data['cookies_enabled'] ?? null,
                ':js_enabled'      => $data['js_enabled'] ?? null,
                ':images_enabled'  => $data['images_enabled'] ?? null,
                ':css_enabled'     => $data['css_enabled'] ?? null,
                ':screen_width'    => $data['screen_width'] ?? null,
                ':screen_height'   => $data['screen_height'] ?? null,
                ':viewport_width'  => $data['viewport_width'] ?? null,
                ':viewport_height' => $data['viewport_height'] ?? null,
                ':pixel_ratio'     => $data['pixel_ratio'] ?? null,
                ':cores'           => $data['cores'] ?? null,
                ':memory'          => $data['memory'] ?? null,
                ':network_type'    => $data['network_type'] ?? null,
                ':color_scheme'    => $data['color_scheme'] ?? null,
                ':timezone'        => $data['timezone'] ?? null,
                ':id'              => $id
            ]);
            echo json_encode(['status' => 'updated']);
            break;

        // DELETE /api/static/{id}
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM static_data WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['status' => 'deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);
    }
}
?>