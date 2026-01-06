<?php
header('Content-Type: application/javascript');
require_once __DIR__ . '/../../includes/base.php';
$baseJs = isset($base) ? $base : '';
?>
window.APP_BASE = <?php echo json_encode($baseJs); ?>;
