<?php
/**
 * Society Branding & Settings API
 * Manages society branding, theme color, logo, welcome message, and emergency contacts.
 * 
 * Supports both Session-based portal authentication and Bearer JWT tokens.
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../society_context_holder.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

$pdo = get_indsac_db();

// 1. Initialize Request-Scoped Context
SocietyContextHolder::initFromRequest($pdo);
$societyId = SocietyContextHolder::getCurrentSocietyId();
$userId = SocietyContextHolder::getCurrentUserId();
$currentRole = SocietyContextHolder::getCurrentRole();

if (!$societyId || $societyId < 1 || !$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required: no active society context found.'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// If POST, decode json input if not multipart/form-data
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
    if (empty($action)) {
        $action = $input['action'] ?? $_POST['action'] ?? '';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. GET: get_branding
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'get_branding' || $action === 'get_settings' || empty($action))) {
    try {
        $stmt = $pdo->prepare('SELECT id, society_id, logo_path, theme_color, welcome_message, emergency_contacts, updated_at FROM society_settings WHERE society_id = ? LIMIT 1');
        $stmt->execute([(int)$societyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Default fallback if no row exists yet
            echo json_encode([
                'success' => true,
                'data' => [
                    'society_id' => (int)$societyId,
                    'logo_path' => null,
                    'theme_color' => null,
                    'welcome_message' => null,
                    'emergency_contacts' => [],
                    'updated_at' => null
                ]
            ]);
            exit;
        }

        $contacts = [];
        if (!empty($row['emergency_contacts'])) {
            $decoded = json_decode($row['emergency_contacts'], true);
            if (is_array($decoded)) {
                $contacts = $decoded;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'society_id' => (int)$row['society_id'],
                'logo_path' => $row['logo_path'],
                'theme_color' => $row['theme_color'],
                'welcome_message' => $row['welcome_message'],
                'emergency_contacts' => $contacts,
                'updated_at' => $row['updated_at']
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error loading society branding settings.'
        ]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. POST: save_branding
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'save_branding' || $action === 'save_settings')) {
    // Permission check: Must be Admin / Superadmin scoped to this society
    $isAdmin = false;
    $normRole = strtoupper(str_replace([' ', '_', '-'], '', (string)$currentRole));
    if (in_array($normRole, ['ADMIN', 'SUPERADMIN', 'SOCIETYADMIN'], true) || !empty($_SESSION['portal_is_superadmin'])) {
        $isAdmin = true;
    } else {
        // Double check against user_society_mapping for this society
        try {
            $stmt = $pdo->prepare('SELECT m.role FROM user_society_mapping m WHERE m.society_id = ? AND (m.user_id = ? OR m.user_id = (SELECT global_user_id FROM employees WHERE id = ? LIMIT 1)) LIMIT 1');
            $stmt->execute([(int)$societyId, (int)$userId, (int)$userId]);
            $dbRole = $stmt->fetchColumn();
            if ($dbRole) {
                $normDbRole = strtoupper(str_replace([' ', '_', '-'], '', (string)$dbRole));
                if (in_array($normDbRole, ['ADMIN', 'SUPERADMIN', 'SOCIETYADMIN'], true)) {
                    $isAdmin = true;
                }
            }
        } catch (Throwable $e) {}
    }

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized: Administrator privileges are required to modify branding for this society.'
        ]);
        exit;
    }

    // Merge $_POST and JSON body
    $data = array_merge($input, $_POST);

    // Fetch existing settings row for comparison / old values
    $existing = null;
    try {
        $stmt = $pdo->prepare('SELECT id, society_id, logo_path, theme_color, welcome_message, emergency_contacts FROM society_settings WHERE society_id = ? LIMIT 1');
        $stmt->execute([(int)$societyId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // ── Field Validation 1: theme_color ──
    $themeColor = isset($data['theme_color']) ? trim((string)$data['theme_color']) : ($existing['theme_color'] ?? null);
    if ($themeColor !== null && $themeColor !== '') {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $themeColor)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid theme_color. Must be a valid 6-character hex code (e.g. #6366f1).'
            ]);
            exit;
        }
    } else {
        $themeColor = null;
    }

    // ── Field Validation 2: welcome_message ──
    $welcomeMessage = isset($data['welcome_message']) ? strip_tags(trim((string)$data['welcome_message'])) : ($existing['welcome_message'] ?? null);
    if ($welcomeMessage !== null && mb_strlen($welcomeMessage, 'UTF-8') > 500) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Welcome message cannot exceed 500 characters.'
        ]);
        exit;
    }
    if ($welcomeMessage === '') {
        $welcomeMessage = null;
    }

    // ── Field Validation 3: emergency_contacts ──
    $allowedLabels = [
        'Security Gate',
        'Society Office',
        'Electrician',
        'Plumber',
        'Police/Ambulance'
    ];

    $rawContacts = $data['emergency_contacts'] ?? null;
    $validatedContacts = [];

    if ($rawContacts !== null) {
        if (is_string($rawContacts)) {
            $rawContacts = json_decode($rawContacts, true);
        }

        if (!is_array($rawContacts)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid emergency_contacts format. Must be an array of {label, phone} items.'
            ]);
            exit;
        }

        foreach ($rawContacts as $idx => $contact) {
            if (!is_array($contact)) continue;
            $label = trim((string)($contact['label'] ?? ''));
            $phone = trim((string)($contact['phone'] ?? ''));

            if ($label === '' && $phone === '') {
                continue; // Skip empty rows
            }

            // Find matching standard label (case-insensitive search)
            $matchedLabel = null;
            foreach ($allowedLabels as $allowed) {
                if (strcasecmp($allowed, $label) === 0) {
                    $matchedLabel = $allowed;
                    break;
                }
            }

            if (!$matchedLabel) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "Unknown contact label '{$label}'. Allowed labels are: " . implode(', ', $allowedLabels) . '.'
                ]);
                exit;
            }

            if ($phone !== '' && !preg_match('/^[0-9+\s\-()]{3,15}$/', $phone)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "Invalid phone number format for '{$matchedLabel}'. Must contain 3 to 15 digits/symbols."
                ]);
                exit;
            }

            $validatedContacts[] = [
                'label' => $matchedLabel,
                'phone' => $phone
            ];
        }
    } else {
        $validatedContacts = $existing['emergency_contacts'] ? (json_decode($existing['emergency_contacts'], true) ?: []) : [];
    }

    $contactsJson = !empty($validatedContacts) ? json_encode($validatedContacts, JSON_UNESCAPED_UNICODE) : null;

    // ── Field Validation 4: logo file upload ──
    $logoPath = $existing['logo_path'] ?? null;

    // Optional removal flag
    if (!empty($data['remove_logo'])) {
        $logoPath = null;
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['logo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'File upload error code: ' . $file['error']
            ]);
            exit;
        }

        // Max 2MB (2097152 bytes)
        if ($file['size'] > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Logo file size exceeds the 2MB maximum limit.'
            ]);
            exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        if (!in_array($ext, $allowedExts, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid file extension. Only PNG, JPG, JPEG, WEBP, and SVG are allowed.'
            ]);
            exit;
        }

        // MIME-type / content validation
        $tmpPath = $file['tmp_name'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpPath) ?: '';
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmpPath);
        }

        $validMime = false;
        if ($ext === 'svg') {
            $content = (string)file_get_contents($tmpPath, false, null, 0, 1024);
            if (stripos($content, '<svg') !== false) {
                $validMime = true;
            }
        } else {
            // Check via getimagesize or magic bytes
            $imgInfo = @getimagesize($tmpPath);
            if ($imgInfo && !empty($imgInfo['mime'])) {
                $mime = $imgInfo['mime'];
                $validMime = match ($ext) {
                    'png' => in_array($mime, ['image/png'], true),
                    'jpg', 'jpeg' => in_array($mime, ['image/jpeg', 'image/pjpeg'], true),
                    'webp' => in_array($mime, ['image/webp'], true),
                    default => false,
                };
            } else {
                // Fallback to header bytes
                $bytes = (string)file_get_contents($tmpPath, false, null, 0, 12);
                if ($ext === 'png' && str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
                    $validMime = true;
                } elseif (($ext === 'jpg' || $ext === 'jpeg') && str_starts_with($bytes, "\xFF\xD8\xFF")) {
                    $validMime = true;
                } elseif ($ext === 'webp' && str_starts_with($bytes, "RIFF") && substr($bytes, 8, 4) === "WEBP") {
                    $validMime = true;
                }
            }
        }

        if (!$validMime) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "File content does not match allowed image type (detected MIME: {$mime})."
            ]);
            exit;
        }

        // Create target directory if needed
        $uploadDir = __DIR__ . '/../uploads/society_logos';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $filename = (int)$societyId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $targetFile = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $targetFile)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to move uploaded logo file to destination.'
            ]);
            exit;
        }

        $logoPath = 'uploads/society_logos/' . $filename;
    }

    // ── Save to Database ──
    try {
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE society_settings SET logo_path = ?, theme_color = ?, welcome_message = ?, emergency_contacts = ? WHERE society_id = ?');
            $stmt->execute([$logoPath, $themeColor, $welcomeMessage, $contactsJson, (int)$societyId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO society_settings (society_id, logo_path, theme_color, welcome_message, emergency_contacts) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([(int)$societyId, $logoPath, $themeColor, $welcomeMessage, $contactsJson]);
        }

        // Fetch updated row
        $stmt = $pdo->prepare('SELECT id, society_id, logo_path, theme_color, welcome_message, emergency_contacts, updated_at FROM society_settings WHERE society_id = ? LIMIT 1');
        $stmt->execute([(int)$societyId]);
        $updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // Audit Log
        $legacyClientId = SocietyContextHolder::getLegacyClientId($pdo);
        $performedBy = (string)($_SESSION['portal_employee_id'] ?? $userId);
        $oldValues = [
            'logo_path' => $existing['logo_path'] ?? null,
            'theme_color' => $existing['theme_color'] ?? null,
            'welcome_message' => $existing['welcome_message'] ?? null,
            'emergency_contacts' => $existing['emergency_contacts'] ?? null,
        ];
        $newValues = [
            'logo_path' => $logoPath,
            'theme_color' => $themeColor,
            'welcome_message' => $welcomeMessage,
            'emergency_contacts' => $contactsJson,
        ];

        logAudit($pdo, $legacyClientId, $performedBy, 'SOCIETY_BRANDING_UPDATED', json_encode([
            'society_id' => (int)$societyId,
            'old' => $oldValues,
            'new' => $newValues
        ]));

        logSocietyAudit($pdo, (int)$societyId, 'SOCIETY_UPDATED', [
            'actor_user_id' => (int)$userId,
            'actor_name'    => (string)($_SESSION['portal_username'] ?? $performedBy),
            'target_id'     => (string)$societyId,
            'target_type'   => 'society_settings',
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
        ]);

        echo json_encode([
            'success' => true,
            'data' => [
                'society_id' => (int)$updatedRow['society_id'],
                'logo_path' => $updatedRow['logo_path'],
                'theme_color' => $updatedRow['theme_color'],
                'welcome_message' => $updatedRow['welcome_message'],
                'emergency_contacts' => $validatedContacts,
                'updated_at' => $updatedRow['updated_at']
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error saving society settings: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Default fallback for unrecognized action
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Invalid action or request method.'
]);
