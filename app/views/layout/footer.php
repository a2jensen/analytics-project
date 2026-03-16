<?php
// Closes the <main>, <body>, and <html> tags opened by header.php.
// Kept as a separate file so every view can include both halves of the layout independently.
?>
</main>
<script>
function exportWithChart(type, id, canvasId) {
    var canvas = document.getElementById(canvasId);
    var chartImage = '';
    if (canvas) {
        chartImage = canvas.toDataURL('image/png');
    }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/api/export.php';
    form.target = '_blank';

    var fields = {type: type, chart_image: chartImage};
    if (id) fields.id = id;

    for (var key in fields) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>
</body>
</html>
