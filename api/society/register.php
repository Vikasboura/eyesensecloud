<?php
/**
 * Register a new society for the authenticated portal super-admin.
 * POST /api/society/register
 */
ob_start();

session_start();
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../db_setup.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['portal_client_id']) || empty($_SESSION['portal_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$allowedTypes = [
    'RESIDENTIAL_SOCIETY', 'APARTMENT', 'TOWNSHIP', 'BOYS_HOSTEL', 'GIRLS_HOSTEL',
    'PG_ACCOMMODATION', 'COMMERCIAL_COMPLEX', 'OFFICE_BUILDING', 'INDUSTRIAL_AREA',
    'SCHOOL', 'COLLEGE', 'HOSPITAL'
];

$societyName = trim($input['societyName'] ?? '');
$societyType = strtoupper(trim($input['societyType'] ?? ''));
$address = trim($input['address'] ?? '');
$city = trim($input['city'] ?? '');
$state = trim($input['state'] ?? '');
$country = trim($input['country'] ?? '');
$pincode = trim($input['pincode'] ?? '');
$contactPerson = trim($input['contactPerson'] ?? '');
$mobileNumber = trim($input['mobileNumber'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));

$required = [
    'societyName' => $societyName,
    'societyType' => $societyType,
    'address' => $address,
    'city' => $city,
    'state' => $state,
    'country' => $country,
    'pincode' => $pincode,
    'contactPerson' => $contactPerson,
    'mobileNumber' => $mobileNumber,
    'email' => $email,
];
foreach ($required as $field => $value) {
    if ($value === '') {
        echo json_encode(['success' => false, 'error' => "{$field} is required"]);
        exit;
    }
}
if (!in_array($societyType, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid society type']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}
if (strcasecmp($country, 'India') === 0 || $country === '') {
    if (!preg_match('/^[6-9]\d{9}$/', $mobileNumber)) {
        echo json_encode(['success' => false, 'error' => 'Mobile number must be a valid 10-digit number starting with 6-9']);
        exit;
    }
} elseif (strcasecmp($country, 'United States') === 0 || strcasecmp($country, 'Canada') === 0) {
    if (!preg_match('/^\d{10}$/', preg_replace('/\D/', '', $mobileNumber))) {
        echo json_encode(['success' => false, 'error' => 'Mobile number must be exactly 10 digits']);
        exit;
    }
} else {
    if (!preg_match('/^[0-9+()\-\s]{7,15}$/', $mobileNumber)) {
        echo json_encode(['success' => false, 'error' => 'Invalid mobile number format']);
        exit;
    }
}
if (strcasecmp($country, 'India') === 0 || $country === '') {
    if (!preg_match('/^\d{6}$/', $pincode)) {
        echo json_encode(['success' => false, 'error' => 'Pincode must be exactly 6 digits for India']);
        exit;
    }
} elseif (strcasecmp($country, 'United States') === 0) {
    if (!preg_match('/^\d{5}$/', $pincode)) {
        echo json_encode(['success' => false, 'error' => 'Zip code must be exactly 5 digits for USA']);
        exit;
    }
} else {
    if (!preg_match('/^[A-Za-z0-9\-\s]{3,10}$/', $pincode)) {
        echo json_encode(['success' => false, 'error' => 'Invalid postal code / pincode']);
        exit;
    }
}

$optional = static function ($value): ?int {
    if ($value === null || $value === '') return null;
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) return null;
    return (int)$value;
};
$numberOfTowers = $optional($input['numberOfTowers'] ?? null);
$numberOfFlats = $optional($input['numberOfFlats'] ?? null);
$numberOfCameras = $optional($input['numberOfCameras'] ?? null);

try {
    $pdo = get_indsac_db();

    $globalUserId = null;
    $empAccessLevel = 'USER';

    // 1. Try resolving from employees table
    if (!empty($_SESSION['portal_user_id'])) {
        $employeeStmt = $pdo->prepare('SELECT global_user_id, access_level FROM employees WHERE id = ? AND status = \'active\' LIMIT 1');
        $employeeStmt->execute([(int)$_SESSION['portal_user_id']]);
        $empRow = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        if ($empRow) {
            $globalUserId = $empRow['global_user_id'] ?? null;
            $empAccessLevel = $empRow['access_level'] ?? 'USER';
        }
    }

    // 2. Try resolving from users table by session email / username
    $sessionEmail = strtolower(trim($_SESSION['portal_username'] ?? ($_SESSION['portal_email'] ?? '')));
    if (!$globalUserId && $sessionEmail !== '') {
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? AND status = \'ACTIVE\' LIMIT 1');
        $uStmt->execute([$sessionEmail]);
        $globalUserId = $uStmt->fetchColumn() ?: null;
    }

    // 3. Try resolving from register table and provision global user if needed
    if (!$globalUserId) {
        $regRow = null;
        if (!empty($_SESSION['portal_user_id'])) {
            $rStmt = $pdo->prepare('SELECT fullname, email, mobile, password FROM register WHERE id = ? LIMIT 1');
            $rStmt->execute([(int)$_SESSION['portal_user_id']]);
            $regRow = $rStmt->fetch();
        }
        if (!$regRow && $sessionEmail !== '') {
            $rStmt = $pdo->prepare('SELECT fullname, email, mobile, password FROM register WHERE LOWER(email) = ? LIMIT 1');
            $rStmt->execute([$sessionEmail]);
            $regRow = $rStmt->fetch();
        }

        if ($regRow) {
            $insUser = $pdo->prepare('INSERT INTO users (full_name, email, mobile, password_hash, status) VALUES (?, ?, ?, ?, \'ACTIVE\') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            $insUser->execute([
                $regRow['fullname'],
                strtolower(trim($regRow['email'])),
                $regRow['mobile'],
                $regRow['password']
            ]);
            $globalUserId = (int)$pdo->lastInsertId();
        }
    }

    if (!$globalUserId) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Your account is not yet linked to a global identity. Please re-login and try again.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $duplicate = $pdo->prepare('SELECT id FROM society WHERE LOWER(society_name) = LOWER(?) AND LOWER(city) = LOWER(?) LIMIT 1');
    $duplicate->execute([$societyName, $city]);
    if ($duplicate->fetchColumn()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'A society with this name already exists in this city.']);
        exit;
    }

    $societyCode = '';
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $societyCode = 'SOC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $codeCheck = $pdo->prepare('SELECT id FROM society WHERE society_code = ?');
        $codeCheck->execute([$societyCode]);
        if (!$codeCheck->fetchColumn()) break;
    }
    if ($societyCode === '') {
        throw new RuntimeException('Unable to generate a society code.');
    }

    $globalUserStmt = $pdo->prepare('SELECT full_name, email, mobile, password_hash FROM users WHERE id = ? AND status = \'ACTIVE\' LIMIT 1');
    $globalUserStmt->execute([(int)$globalUserId]);
    $globalUser = $globalUserStmt->fetch();
    if (!$globalUser) {
        throw new RuntimeException('Global user identity is unavailable.');
    }

    $defaultCheck = $pdo->prepare('SELECT 1 FROM user_society_mapping
        WHERE user_id = ? AND status = \'ACTIVE\' AND is_default = 1 LIMIT 1');
    $defaultCheck->execute([(int)$globalUserId]);
    $isDefault = $defaultCheck->fetchColumn() === false;

    $legacyClientId = generate_client_id($pdo);
    $contactParts = preg_split('/\s+/', $contactPerson, 2);
    $contactFirstName = $contactParts[0] ?? $contactPerson;
    $contactLastName = $contactParts[1] ?? '';
    $clientInsert = $pdo->prepare('INSERT INTO clients (client_id, first_name, last_name, email, mobile, sa_username) VALUES (?, ?, ?, ?, ?, ?)');
    $clientInsert->execute([$legacyClientId, $contactFirstName, $contactLastName, $email, $mobileNumber, 'USR-' . $globalUserId]);

    $employeeId = 'SA-' . strtoupper(substr(str_replace('-', '', $legacyClientId), -10));
    $creatorEmployeeId = (string)($_SESSION['portal_employee_id'] ?? $employeeId);
    $employeeInsert = $pdo->prepare('INSERT INTO employees
        (global_user_id, client_id, employee_id, full_name, email, phone, role, access_level, password_hash, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, \'superadmin\', \'ADMIN\', ?, \'active\', ?)');
    $employeeInsert->execute([
        (int)$globalUserId, $legacyClientId, $employeeId, $globalUser['full_name'],
        $globalUser['email'], $globalUser['mobile'], $globalUser['password_hash'],
        $creatorEmployeeId
    ]);

    $insert = $pdo->prepare('INSERT INTO society
        (legacy_client_id, society_code, society_name, society_type, address, city, state, country, pincode,
         contact_person, mobile_number, email, number_of_towers, number_of_flats, number_of_cameras)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $legacyClientId, $societyCode, $societyName, $societyType, $address, $city, $state, $country, $pincode,
        $contactPerson, $mobileNumber, $email, $numberOfTowers, $numberOfFlats, $numberOfCameras
    ]);
    $societyId = (int)$pdo->lastInsertId();

    $settings = $pdo->prepare('INSERT INTO society_settings (society_id) VALUES (?)');
    $settings->execute([$societyId]);

    $mappingCheck = $pdo->prepare('SELECT id FROM user_society_mapping WHERE user_id = ? AND society_id = ? LIMIT 1');
    $mappingCheck->execute([(int)$globalUserId, $societyId]);
    if ($mappingCheck->fetchColumn()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This user is already mapped to the society.']);
        exit;
    }

    $mappingRole = (!empty($_SESSION['portal_is_superadmin']) || strtoupper($empAccessLevel) === 'ADMIN') ? 'SUPER_ADMIN' : 'SOCIETY_ADMIN';

    $mapping = $pdo->prepare('INSERT INTO user_society_mapping (user_id, society_id, role, is_default) VALUES (?, ?, ?, ?)');
    $mapping->execute([(int)$globalUserId, $societyId, $mappingRole, $isDefault ? 1 : 0]);

    logAudit($pdo, null, $creatorEmployeeId, 'SOCIETY_CREATED', json_encode([
        'society_id' => $societyId,
        'society_code' => $societyCode,
        'society_name' => $societyName
    ]));

    logSocietyAudit($pdo, (int)$societyId, 'SOCIETY_CREATED', [
        'actor_user_id' => (int)$globalUserId,
        'actor_name'    => (string)($_SESSION['portal_username'] ?? $_SESSION['portal_employee_id'] ?? 'Admin'),
        'target_id'     => (string)$societyId,
        'target_type'   => 'society',
        'new_values'    => [
            'society_name' => $societyName,
            'society_code' => $societyCode,
            'society_type' => $societyType,
            'legacy_client_id' => $legacyClientId,
        ]
    ]);

    logSocietyAudit($pdo, (int)$societyId, 'USER_ADDED', [
        'actor_user_id' => (int)$globalUserId,
        'actor_name'    => (string)($_SESSION['portal_username'] ?? $_SESSION['portal_employee_id'] ?? 'Admin'),
        'target_id'     => (string)$globalUserId,
        'target_type'   => 'user',
        'new_values'    => [
            'role'       => $mappingRole,
            'is_default' => $isDefault ? 1 : 0,
        ]
    ]);

    $pdo->commit();
    portal_refresh_available_societies($pdo, (int)$globalUserId);
    logger_info("Society registered successfully", [
        'society_id' => $societyId,
        'society_code' => $societyCode,
        'global_user_id' => $globalUserId
    ]);
    echo json_encode([
        'success' => true,
        'societyId' => $societyId,
        'clientId' => $legacyClientId,
        'societyCode' => $societyCode,
        'societyName' => $societyName,
        'message' => 'Society registered successfully.'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logger_error('Society registration error: ' . $e->getMessage(), [
        'user_id' => $_SESSION['portal_user_id'] ?? null,
        'email' => $email ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Society registration failed: ' . $e->getMessage()]);
}
