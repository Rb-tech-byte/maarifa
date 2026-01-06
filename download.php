<?php
// Deprecated endpoint: redirect to consolidated handler
$token = isset($_GET['token']) ? $_GET['token'] : '';
header('Location: downloads.php' . ($token !== '' ? ('?token=' . urlencode($token)) : ''));
exit;
