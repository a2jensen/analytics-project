<?php
// CRUD handler for performance_data. Same structure as static.php but for page-load timing metrics.
require_once 'db.php';

// Routes GET, POST, PUT, DELETE to the appropriate SQL against performance_data.
// php://input is used for POST/PUT for the same reason as in static.php — the body is JSON,
// not form-encoded, so $_POST would be empty.
function handlePerformance($method, $id) {
    global $pdo;

    switch ($method) {
        // GET /api/performance or GET /api/performance/{id}
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM performance_data WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['error' => 'not found']);
                } else {
                    echo json_encode($row);
                }
            } else {
                $stmt = $pdo->query("SELECT * FROM performance_data");
                echo json_encode($stmt->fetchAll());
            }
            break;

        // POST /api/performance
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $pdo->prepare("
                INSERT INTO performance_data (
                    session_id, url, page_load_start, page_load_end, total_load_time,
                    dns_lookup, tcp_connect, tls_handshake, ttfb, download,
                    dom_interactive, dom_complete, load_event, fetch_time,
                    transfer_size, header_size, total_resources, lcp, cls, inp
                ) VALUES (
                    :session_id, :url, :page_load_start, :page_load_end, :total_load_time,
                    :dns_lookup, :tcp_connect, :tls_handshake, :ttfb, :download,
                    :dom_interactive, :dom_complete, :load_event, :fetch_time,
                    :transfer_size, :header_size, :total_resources, :lcp, :cls, :inp
                )
            ");
            $stmt->execute([
                ':session_id'      => $data['session_id'] ?? null,
                ':url'             => $data['url'] ?? null,
                ':page_load_start' => $data['page_load_start'] ?? null,
                ':page_load_end'   => $data['page_load_end'] ?? null,
                ':total_load_time' => $data['total_load_time'] ?? null,
                ':dns_lookup'      => $data['dns_lookup'] ?? null,
                ':tcp_connect'     => $data['tcp_connect'] ?? null,
                ':tls_handshake'   => $data['tls_handshake'] ?? null,
                ':ttfb'            => $data['ttfb'] ?? null,
                ':download'        => $data['download'] ?? null,
                ':dom_interactive' => $data['dom_interactive'] ?? null,
                ':dom_complete'    => $data['dom_complete'] ?? null,
                ':load_event'      => $data['load_event'] ?? null,
                ':fetch_time'      => $data['fetch_time'] ?? null,
                ':transfer_size'   => $data['transfer_size'] ?? null,
                ':header_size'     => $data['header_size'] ?? null,
                ':total_resources' => $data['total_resources'] ?? null,
                ':lcp'             => $data['lcp'] ?? null,
                ':cls'             => $data['cls'] ?? null,
                ':inp'             => $data['inp'] ?? null
            ]);
            http_response_code(201);
            echo json_encode(['status' => 'created', 'id' => $pdo->lastInsertId()]);
            break;

        // PUT /api/performance/{id}
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $pdo->prepare("
                UPDATE performance_data SET
                    session_id = :session_id,
                    url = :url,
                    page_load_start = :page_load_start,
                    page_load_end = :page_load_end,
                    total_load_time = :total_load_time,
                    dns_lookup = :dns_lookup,
                    tcp_connect = :tcp_connect,
                    tls_handshake = :tls_handshake,
                    ttfb = :ttfb,
                    download = :download,
                    dom_interactive = :dom_interactive,
                    dom_complete = :dom_complete,
                    load_event = :load_event,
                    fetch_time = :fetch_time,
                    transfer_size = :transfer_size,
                    header_size = :header_size,
                    total_resources = :total_resources,
                    lcp = :lcp,
                    cls = :cls,
                    inp = :inp
                WHERE id = :id
            ");
            $stmt->execute([
                ':session_id'      => $data['session_id'] ?? null,
                ':url'             => $data['url'] ?? null,
                ':page_load_start' => $data['page_load_start'] ?? null,
                ':page_load_end'   => $data['page_load_end'] ?? null,
                ':total_load_time' => $data['total_load_time'] ?? null,
                ':dns_lookup'      => $data['dns_lookup'] ?? null,
                ':tcp_connect'     => $data['tcp_connect'] ?? null,
                ':tls_handshake'   => $data['tls_handshake'] ?? null,
                ':ttfb'            => $data['ttfb'] ?? null,
                ':download'        => $data['download'] ?? null,
                ':dom_interactive' => $data['dom_interactive'] ?? null,
                ':dom_complete'    => $data['dom_complete'] ?? null,
                ':load_event'      => $data['load_event'] ?? null,
                ':fetch_time'      => $data['fetch_time'] ?? null,
                ':transfer_size'   => $data['transfer_size'] ?? null,
                ':header_size'     => $data['header_size'] ?? null,
                ':total_resources' => $data['total_resources'] ?? null,
                ':lcp'             => $data['lcp'] ?? null,
                ':cls'             => $data['cls'] ?? null,
                ':inp'             => $data['inp'] ?? null,
                ':id'              => $id
            ]);
            echo json_encode(['status' => 'updated']);
            break;

        // DELETE /api/performance/{id}
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM performance_data WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['status' => 'deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);
    }
}
?>