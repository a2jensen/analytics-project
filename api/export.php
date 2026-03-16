<?php

require_once 'db.php';
require_once '../vendor/autoload.php';

date_default_timezone_set('America/Los_Angeles');

use Dompdf\Dompdf;

// Accept both GET (legacy) and POST (with chart image)
$type = $_GET['type'] ?? $_POST['type'] ?? null;
$chartImage = $_POST['chart_image'] ?? null;

if (!$type) {
    http_response_code(400);
    echo "Report type required";
    exit;
}

try {

    switch ($type) {

        case 'activity':
            $stmt = $pdo->query("SELECT * FROM activity_data");
            $title = "Activity Analytics Report";
            break;

        case 'performance':
            $stmt = $pdo->query("SELECT * FROM performance_data");
            $title = "Performance Analytics Report";
            break;

        case 'static':
            $stmt = $pdo->query("SELECT * FROM static_data");
            $title = "Static Analytics Report";
            break;

        case 'saved':
            $reportId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($reportId <= 0) {
                http_response_code(400);
                echo "Report ID required";
                exit;
            }
            $rStmt = $pdo->prepare(
                "SELECT r.*, u.username AS created_by_username
                 FROM reports r
                 JOIN users u ON u.id = r.created_by
                 WHERE r.id = :id LIMIT 1"
            );
            $rStmt->execute([':id' => $reportId]);
            $report = $rStmt->fetch(PDO::FETCH_ASSOC);
            if (!$report) {
                http_response_code(404);
                echo "Report not found";
                exit;
            }
            $rows = json_decode($report['snapshot_data'], true) ?: [];
            if (empty($rows)) {
                http_response_code(404);
                echo "No data in snapshot";
                exit;
            }
            $title = $report['title'];
            $savedMeta = '<p><strong>Section:</strong> ' . htmlspecialchars(ucfirst($report['section']))
                . ' &middot; <strong>Created by:</strong> ' . htmlspecialchars($report['created_by_username'])
                . ' &middot; <strong>Saved:</strong> ' . htmlspecialchars($report['created_at']) . '</p>';
            if (!empty($report['commentary'])) {
                $savedMeta .= '<div style="border-left:3px solid #3b82f6; padding:8px 12px; margin:10px 0; background:#f0f7ff;">'
                    . '<strong style="font-size:12px;">Analyst Commentary</strong><br>'
                    . '<span style="font-size:11px;">' . nl2br(htmlspecialchars($report['commentary'])) . '</span>'
                    . '</div>';
            }
            // Skip the normal $stmt->fetchAll below — $rows is already set
            $skipFetch = true;
            break;

        default:
            http_response_code(400);
            echo "Invalid report type";
            exit;
    }

    if (empty($skipFetch)) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$rows) {
        http_response_code(404);
        echo "No data found";
        exit;
    }

    ob_start();
?>

<style>
body {
    font-family: Arial, sans-serif;
    color: #333;
}

h1 {
    text-align: center;
    margin-bottom: 4px;
}

h2 {
    font-size: 14px;
    margin-top: 20px;
    border-bottom: 2px solid #2c3e50;
    padding-bottom: 4px;
}

.meta {
    text-align: center;
    font-size: 11px;
    color: #666;
    margin-bottom: 10px;
}

.chart-container {
    text-align: center;
    margin: 15px 0;
}

.chart-container img {
    max-width: 100%;
    height: auto;
}

table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}

th, td {
    border: 1px solid #ccc;
    padding: 6px;
    font-size: 11px;
    word-wrap: break-word;
}

th {
    background-color: #2c3e50;
    color: #fff;
    font-size: 10px;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}
</style>

<h1><?php echo htmlspecialchars($title); ?></h1>

<p class="meta"><strong>Generated:</strong> <?php echo date("Y-m-d H:i:s"); ?></p>

<?php if (!empty($savedMeta)) echo $savedMeta; ?>

<?php if ($chartImage && str_starts_with($chartImage, 'data:image/')): ?>
<h2>Chart</h2>
<div class="chart-container">
    <img src="<?php echo $chartImage; ?>" />
</div>
<?php endif; ?>

<h2>Data</h2>
<table>

<tr>
<?php foreach(array_keys($rows[0]) as $column): ?>
<th><?php echo htmlspecialchars($column); ?></th>
<?php endforeach; ?>
</tr>

<?php foreach($rows as $row): ?>
<tr>
<?php foreach($row as $cell): ?>
<td><?php echo htmlspecialchars($cell); ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>

</table>

<?php

$html = ob_get_clean();

$dompdf = new Dompdf(['isRemoteEnabled' => true]);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');   // landscape prevents column cutoff
$dompdf->render();

/*
Stream PDF directly to browser
Attachment=false → open in browser instead of downloading
*/

$filename = ($type === 'saved') ? "saved_report_" . ($reportId ?? 0) . ".pdf" : "report_" . $type . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);

} catch (Exception $e) {

    http_response_code(500);
    echo "Server error";
}