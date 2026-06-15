<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? '';
$key = $_GET['key'] ?? '';
$hwid = $_GET['hwid'] ?? '';

$licensesFile = 'licenses.json';

if (!file_exists($licensesFile)) {
    file_put_contents($licensesFile, json_encode([]));
}

$licenses = json_decode(file_get_contents($licensesFile), true);

// Validation
if ($action === 'validate') {
    $cleanKey = str_replace('-', '', $key);
    
    if (!isset($licenses[$cleanKey])) {
        echo json_encode(['success' => false, 'reason' => 'Invalid key']);
        exit;
    }
    
    $license = $licenses[$cleanKey];
    $now = time();
    
    if ($license['expires'] > 0 && $license['expires'] < $now) {
        echo json_encode(['success' => false, 'reason' => 'Key expired']);
        exit;
    }
    
    if (!empty($license['hwid']) && $license['hwid'] !== $hwid) {
        echo json_encode(['success' => false, 'reason' => 'Used on another PC']);
        exit;
    }
    
    if (empty($license['hwid'])) {
        $licenses[$cleanKey]['hwid'] = $hwid;
        file_put_contents($licensesFile, json_encode($licenses, JSON_PRETTY_PRINT));
    }
    
    echo json_encode(['success' => true, 'expires' => $license['expires']]);
    exit;
}

// Admin - Lister les clés
if ($action === 'admin_list') {
    $adminKey = $_GET['adminKey'] ?? '';
    if ($adminKey !== 'admin123') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $result = [];
    foreach ($licenses as $k => $data) {
        $result[] = [
            'key' => $k,
            'expires' => $data['expires'],
            'hwid' => $data['hwid'] ?? '',
            'created' => $data['created']
        ];
    }
    echo json_encode($result);
    exit;
}

// Admin - Créer une clé
if ($action === 'admin_create') {
    $adminKey = $_GET['adminKey'] ?? '';
    if ($adminKey !== 'admin123') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $days = intval($_GET['days'] ?? 30);
    $durationHex = strtoupper(dechex($days));
    $random = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    $rawKey = 'AIMWARE' . str_pad($durationHex, 4, '0', STR_PAD_LEFT) . $random;
    $formattedKey = substr($rawKey, 0, 7) . '-' . substr($rawKey, 7, 4) . '-' . 
                    substr($rawKey, 11, 4) . '-' . substr($rawKey, 15, 4);
    
    $expires = $days === 0 ? 0 : time() + ($days * 86400);
    
    $licenses[$rawKey] = [
        'expires' => $expires,
        'hwid' => '',
        'created' => time()
    ];
    
    file_put_contents($licensesFile, json_encode($licenses, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'key' => $formattedKey]);
    exit;
}

// Admin - Supprimer
if ($action === 'admin_delete') {
    $adminKey = $_GET['adminKey'] ?? '';
    if ($adminKey !== 'admin123') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $key = str_replace('-', '', $_GET['key'] ?? '');
    if (isset($licenses[$key])) {
        unset($licenses[$key]);
        file_put_contents($licensesFile, json_encode($licenses, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Key not found']);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action']);
?>
