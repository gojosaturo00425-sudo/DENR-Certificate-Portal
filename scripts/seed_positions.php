<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/config.php';

$positions = [
    'Administrative Aide',
    'Administrative Assistant',
    'Administrative Officer',
    'Accountant',
    'Attorney',
    'Community Affairs Officer',
    'Ecosystems Management Specialist',
    'Engineer',
    'Forester',
    'Forest Ranger',
    'Geodetic Engineer',
    'Information Systems Analyst',
    'Planning Officer',
    'Science Research Specialist',
    'Other',
];

$insert = $pdo->prepare('INSERT IGNORE INTO positions (name) VALUES (?)');
foreach ($positions as $position) {
    $insert->execute([$position]);
}

$count = (int)$pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn();
fwrite(STDOUT, "Position catalog ready ($count positions in database).\n");
