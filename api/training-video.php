<?php
declare(strict_types=1);

define('PORTAL_API_REQUEST', true);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/training_video.php';

$id = (int)($_GET['id'] ?? 0);
serveTrainingVideoById($pdo, $id);
