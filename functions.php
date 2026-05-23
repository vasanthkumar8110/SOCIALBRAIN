<?php
// functions.php - Core helper functions for SocialBrain

// --- Configuration & Constants ---
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__);
if (!defined('MASTER_BRAIN_FILE')) define('MASTER_BRAIN_FILE', DATA_DIR . '/master_brain.json');
if (!defined('ACTIVITY_LOG_FILE')) define('ACTIVITY_LOG_FILE', DATA_DIR . '/activity_log.json');
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour

// --- Helper Functions ---

function loadJSON($filepath) {
    if (empty($filepath) || !file_exists($filepath)) return [];
    $content = file_get_contents($filepath);
    if ($content === false) return [];
    return json_decode($content, true) ?: [];
}

function saveJSON($filepath, $data) {
    if (empty($filepath)) return false;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    // --- Safety: Backup previous state ---
    if (file_exists($filepath)) {
        @copy($filepath, $filepath . '.bak');
    }

    $tempFile = $filepath . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($tempFile, $json, LOCK_EX) !== false) {
        if (@rename($tempFile, $filepath)) {
            if ($filepath === MASTER_BRAIN_FILE && is_array($data)) {
                sb_sync_master_brain_to_sql($data);
            }
            return true;
        }
        @unlink($tempFile);
    }
    return false;
}

function generateID($prefix = '') {
    return $prefix . bin2hex(random_bytes(10));
}

function getTeamBrainFile($teamId) {
    if (!$teamId) return null;
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $teamId);
    return DATA_DIR . '/master_brain_' . $safeId . '.json';
}

function logActivity($userId, $action, $details = []) {
    $masterData = loadJSON(MASTER_BRAIN_FILE);
    $teamId = $_SESSION['team_id'] ?? null;
    $newLog = [
        'id' => generateID('log_'),
        'timestamp' => date('c'),
        'user_id' => $userId,
        'team_id' => $teamId,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    if (!isset($masterData['logs']) || !is_array($masterData['logs'])) {
        $masterData['logs'] = [];
    }

    array_unshift($masterData['logs'], $newLog);

    if (count($masterData['logs']) > 1000) {
        $masterData['logs'] = array_slice($masterData['logs'], 0, 1000);
    }

    saveJSON(MASTER_BRAIN_FILE, $masterData);
}

function jsonResponse($data, $success = true, $message = '') {
    // Ensure headers are sent only once
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

// --- Secure Session Bootstrap ---
function sb_session_start_secure() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // Cookie params must be set BEFORE session_start()
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    if ($isSecure) {
        @ini_set('session.cookie_secure', '1');
    }

    session_start();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function sb_require_csrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $sess = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sess) || $sess === '' || !is_string($token) || $token === '' || !hash_equals($sess, $token)) {
        http_response_code(403);
        jsonResponse(null, false, 'CSRF validation failed');
    }
}

// --- Authentication Helper ---

/**
 * Checks authentication via API Key or Session.
 * Returns the role if authenticated, false otherwise.
 * Populates global $currentUser with user details.
 */
function checkAuthAndGetRole() {
    global $currentUser;
    $currentUser = null;

    // 1. Check for API Key (Public API Access)
    $apiKeyInput = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if (!$apiKeyInput) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (is_string($auth) && stripos($auth, 'Bearer ') === 0) {
            $apiKeyInput = trim(substr($auth, 7));
        }
    }
    if ($apiKeyInput) {
        $masterData = loadJSON(MASTER_BRAIN_FILE);
        $apiKeys = $masterData['api_keys'] ?? [];
        foreach ($apiKeys as $key) {
            if ($key['key'] === $apiKeyInput && ($key['status'] ?? 'active') === 'active') {
                $role = $key['role'] ?? 'user';
                $currentUser = [
                    'id' => 'api_key_' . $key['id'], // Virtual ID for API users
                    'username' => $key['label'] ?? 'API User',
                    'role' => $role,
                    'is_api' => true
                ];
                return $role; 
            }
        }
    }

    // 2. Standard Session Auth
    if (session_status() === PHP_SESSION_NONE) {
        sb_session_start_secure();
    }

    if (isset($_SESSION['user_id'])) {
        // Check for session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        $currentUser = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'User',
            'role' => $_SESSION['role'] ?? 'user',
            'is_api' => false
        ];
        return $_SESSION['role'] ?? 'user';
    }

    return false;
}

function isAdmin() {
    global $currentUser;
    if (!$currentUser) {
        checkAuthAndGetRole();
    }
    return ($currentUser['role'] === 'admin');
}

function sb_get_db() {
    static $pdo = null;
    static $failed = false;
    if ($pdo !== null || $failed) {
        return $pdo;
    }
    $host = getenv('SB_DB_HOST');
    if ($host === false || $host === '') {
        $host = $_SERVER['SB_DB_HOST'] ?? ($_ENV['SB_DB_HOST'] ?? '127.0.0.1');
    }
    $name = getenv('SB_DB_NAME');
    if ($name === false || $name === '') {
        $name = $_SERVER['SB_DB_NAME'] ?? ($_ENV['SB_DB_NAME'] ?? '');
    }
    $user = getenv('SB_DB_USER');
    if ($user === false || $user === '') {
        $user = $_SERVER['SB_DB_USER'] ?? ($_ENV['SB_DB_USER'] ?? '');
    }
    $pass = getenv('SB_DB_PASS');
    if ($pass === false) {
        $pass = $_SERVER['SB_DB_PASS'] ?? ($_ENV['SB_DB_PASS'] ?? '');
    }
    if ($name === '' || $user === '') {
        $failed = true;
        return null;
    }
    try {
        $baseDsn = "mysql:host={$host};charset=utf8mb4";
        $basePdo = new PDO($baseDsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $basePdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Throwable $e) {
        $failed = true;
        $pdo = null;
    }
    return $pdo;
}

function sb_ensure_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_users (
        id VARCHAR(64) PRIMARY KEY,
        username VARCHAR(191) NOT NULL,
        password_hash TEXT NULL,
        role VARCHAR(32) DEFAULT 'viewer',
        payment_status VARCHAR(32) DEFAULT NULL,
        created_at VARCHAR(64) DEFAULT NULL,
        is_disabled TINYINT(1) DEFAULT 0,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_posts (
        id VARCHAR(64) PRIMARY KEY,
        platform_id VARCHAR(64) DEFAULT NULL,
        date VARCHAR(32) DEFAULT NULL,
        post_time VARCHAR(32) DEFAULT NULL,
        caption TEXT,
        media_url TEXT,
        type VARCHAR(32) DEFAULT NULL,
        product_id VARCHAR(64) DEFAULT NULL,
        product_name VARCHAR(191) DEFAULT NULL,
        status VARCHAR(32) DEFAULT NULL,
        created_at VARCHAR(64) DEFAULT NULL,
        updated_at VARCHAR(64) DEFAULT NULL,
        created_by VARCHAR(64) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL,
        deleted_at VARCHAR(64) DEFAULT NULL,
        deleted_by VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_products (
        id VARCHAR(64) PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        sector_id VARCHAR(64) DEFAULT NULL,
        category_id VARCHAR(64) DEFAULT NULL,
        sub_category_id VARCHAR(64) DEFAULT NULL,
        print_shop VARCHAR(191) DEFAULT NULL,
        product_size VARCHAR(191) DEFAULT NULL,
        code VARCHAR(191) DEFAULT NULL,
        price DECIMAL(12,2) DEFAULT 0,
        cost DECIMAL(12,2) DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        updated_at VARCHAR(64) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL,
        created_by VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_sectors (
        id VARCHAR(64) PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        description TEXT,
        active TINYINT(1) DEFAULT 1,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_categories (
        id VARCHAR(64) PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        sector_id VARCHAR(64) DEFAULT NULL,
        active TINYINT(1) DEFAULT 1,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_sub_categories (
        id VARCHAR(64) PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        category_id VARCHAR(64) DEFAULT NULL,
        active TINYINT(1) DEFAULT 1,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_tasks (
        id VARCHAR(64) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        status VARCHAR(32) DEFAULT 'pending',
        date VARCHAR(32) DEFAULT NULL,
        start_date VARCHAR(32) DEFAULT NULL,
        end_date VARCHAR(32) DEFAULT NULL,
        priority VARCHAR(16) DEFAULT NULL,
        due_date VARCHAR(32) DEFAULT NULL,
        due_time VARCHAR(32) DEFAULT NULL,
        list_id VARCHAR(64) DEFAULT NULL,
        assigned_to VARCHAR(64) DEFAULT NULL,
        tags TEXT,
        recurring TEXT,
        created_at VARCHAR(64) DEFAULT NULL,
        updated_at VARCHAR(64) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL,
        created_by VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_logs (
        id VARCHAR(64) PRIMARY KEY,
        timestamp VARCHAR(64) NOT NULL,
        user_id VARCHAR(64) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL,
        action VARCHAR(191) NOT NULL,
        details JSON NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_platforms (
        id VARCHAR(64) PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        code VARCHAR(64) DEFAULT NULL,
        active TINYINT(1) DEFAULT 1,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_holidays (
        id VARCHAR(64) PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        date VARCHAR(32) DEFAULT NULL,
        country VARCHAR(191) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_api_keys (
        id VARCHAR(64) PRIMARY KEY,
        key_value VARCHAR(191) NOT NULL,
        label VARCHAR(191) DEFAULT NULL,
        role VARCHAR(32) DEFAULT 'viewer',
        status VARCHAR(32) DEFAULT 'active',
        created_at VARCHAR(64) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_invites (
        token VARCHAR(64) PRIMARY KEY,
        created_by VARCHAR(64) DEFAULT NULL,
        team_id VARCHAR(64) DEFAULT NULL,
        target_role VARCHAR(32) DEFAULT NULL,
        created_at VARCHAR(64) DEFAULT NULL,
        expires_at VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sb_rd_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id VARCHAR(64) DEFAULT NULL,
        type VARCHAR(64) NOT NULL,
        snapshot LONGTEXT NOT NULL,
        updated_at VARCHAR(64) DEFAULT NULL,
        UNIQUE KEY uniq_team_type (team_id, type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function sb_sync_master_brain_to_sql(array $masterData) {
    try {
        $pdo = sb_get_db();
        if (!$pdo) {
            return;
        }
        sb_ensure_schema($pdo);

        if (isset($masterData['users']) && is_array($masterData['users'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_users (id, username, password_hash, role, payment_status, created_at, is_disabled, team_id)
            VALUES (:id, :username, :password_hash, :role, :payment_status, :created_at, :is_disabled, :team_id)
            ON DUPLICATE KEY UPDATE username = VALUES(username), password_hash = VALUES(password_hash), role = VALUES(role), payment_status = VALUES(payment_status), created_at = VALUES(created_at), is_disabled = VALUES(is_disabled), team_id = VALUES(team_id)");
        foreach ($masterData['users'] as $u) {
            $stmt->execute([
                ':id' => $u['id'] ?? null,
                ':username' => $u['username'] ?? '',
                ':password_hash' => $u['password_hash'] ?? null,
                ':role' => $u['role'] ?? 'viewer',
                ':payment_status' => $u['payment_status'] ?? null,
                ':created_at' => $u['created_at'] ?? null,
                ':is_disabled' => !empty($u['is_disabled']) ? 1 : 0,
                ':team_id' => $u['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['posts']) && is_array($masterData['posts'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_posts (id, platform_id, date, post_time, caption, media_url, type, product_id, product_name, status, created_at, updated_at, created_by, team_id, deleted_at, deleted_by)
            VALUES (:id, :platform_id, :date, :post_time, :caption, :media_url, :type, :product_id, :product_name, :status, :created_at, :updated_at, :created_by, :team_id, :deleted_at, :deleted_by)
            ON DUPLICATE KEY UPDATE platform_id = VALUES(platform_id), date = VALUES(date), post_time = VALUES(post_time), caption = VALUES(caption), media_url = VALUES(media_url), type = VALUES(type), product_id = VALUES(product_id), product_name = VALUES(product_name), status = VALUES(status), created_at = VALUES(created_at), updated_at = VALUES(updated_at), created_by = VALUES(created_by), team_id = VALUES(team_id), deleted_at = VALUES(deleted_at), deleted_by = VALUES(deleted_by)");
        foreach ($masterData['posts'] as $p) {
            $stmt->execute([
                ':id' => $p['id'] ?? null,
                ':platform_id' => $p['platform_id'] ?? null,
                ':date' => $p['date'] ?? null,
                ':post_time' => $p['post_time'] ?? null,
                ':caption' => $p['caption'] ?? null,
                ':media_url' => $p['media_url'] ?? null,
                ':type' => $p['type'] ?? null,
                ':product_id' => $p['product_id'] ?? null,
                ':product_name' => $p['product_name'] ?? null,
                ':status' => $p['status'] ?? null,
                ':created_at' => $p['created_at'] ?? null,
                ':updated_at' => $p['updated_at'] ?? null,
                ':created_by' => $p['created_by'] ?? null,
                ':team_id' => $p['team_id'] ?? null,
                ':deleted_at' => $p['deleted_at'] ?? null,
                ':deleted_by' => $p['deleted_by'] ?? null
            ]);
        }
    }

        if (isset($masterData['products']) && is_array($masterData['products'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_products (id, name, sector_id, category_id, sub_category_id, print_shop, product_size, code, price, cost, active, updated_at, team_id, created_by)
            VALUES (:id, :name, :sector_id, :category_id, :sub_category_id, :print_shop, :product_size, :code, :price, :cost, :active, :updated_at, :team_id, :created_by)
            ON DUPLICATE KEY UPDATE name = VALUES(name), sector_id = VALUES(sector_id), category_id = VALUES(category_id), sub_category_id = VALUES(sub_category_id), print_shop = VALUES(print_shop), product_size = VALUES(product_size), code = VALUES(code), price = VALUES(price), cost = VALUES(cost), active = VALUES(active), updated_at = VALUES(updated_at), team_id = VALUES(team_id), created_by = VALUES(created_by)");
        foreach ($masterData['products'] as $p) {
            $stmt->execute([
                ':id' => $p['id'] ?? null,
                ':name' => $p['name'] ?? '',
                ':sector_id' => $p['sector_id'] ?? null,
                ':category_id' => $p['category_id'] ?? null,
                ':sub_category_id' => $p['sub_category_id'] ?? null,
                ':print_shop' => $p['print_shop'] ?? null,
                ':product_size' => $p['product_size'] ?? null,
                ':code' => $p['code'] ?? null,
                ':price' => isset($p['price']) ? (float)$p['price'] : 0,
                ':cost' => isset($p['cost']) ? (float)$p['cost'] : 0,
                ':active' => !empty($p['active']) ? 1 : 0,
                ':updated_at' => $p['updated_at'] ?? null,
                ':team_id' => $p['team_id'] ?? null,
                ':created_by' => $p['created_by'] ?? null
            ]);
        }
    }

        if (isset($masterData['sectors']) && is_array($masterData['sectors'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_sectors (id, name, description, active, team_id)
            VALUES (:id, :name, :description, :active, :team_id)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), active = VALUES(active), team_id = VALUES(team_id)");
        foreach ($masterData['sectors'] as $s) {
            $stmt->execute([
                ':id' => $s['id'] ?? null,
                ':name' => $s['name'] ?? '',
                ':description' => $s['description'] ?? null,
                ':active' => !empty($s['active']) ? 1 : 0,
                ':team_id' => $s['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['categories']) && is_array($masterData['categories'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_categories (id, name, sector_id, active, team_id)
            VALUES (:id, :name, :sector_id, :active, :team_id)
            ON DUPLICATE KEY UPDATE name = VALUES(name), sector_id = VALUES(sector_id), active = VALUES(active), team_id = VALUES(team_id)");
        foreach ($masterData['categories'] as $c) {
            $stmt->execute([
                ':id' => $c['id'] ?? null,
                ':name' => $c['name'] ?? '',
                ':sector_id' => $c['sector_id'] ?? null,
                ':active' => !empty($c['active']) ? 1 : 0,
                ':team_id' => $c['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['sub_categories']) && is_array($masterData['sub_categories'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_sub_categories (id, name, category_id, active, team_id)
            VALUES (:id, :name, :category_id, :active, :team_id)
            ON DUPLICATE KEY UPDATE name = VALUES(name), category_id = VALUES(category_id), active = VALUES(active), team_id = VALUES(team_id)");
        foreach ($masterData['sub_categories'] as $sc) {
            $stmt->execute([
                ':id' => $sc['id'] ?? null,
                ':name' => $sc['name'] ?? '',
                ':category_id' => $sc['category_id'] ?? null,
                ':active' => !empty($sc['active']) ? 1 : 0,
                ':team_id' => $sc['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['tasks']) && is_array($masterData['tasks'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_tasks (id, title, status, date, start_date, end_date, priority, due_date, due_time, list_id, assigned_to, tags, recurring, created_at, updated_at, team_id, created_by)
            VALUES (:id, :title, :status, :date, :start_date, :end_date, :priority, :due_date, :due_time, :list_id, :assigned_to, :tags, :recurring, :created_at, :updated_at, :team_id, :created_by)
            ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), date = VALUES(date), start_date = VALUES(start_date), end_date = VALUES(end_date), priority = VALUES(priority), due_date = VALUES(due_date), due_time = VALUES(due_time), list_id = VALUES(list_id), assigned_to = VALUES(assigned_to), tags = VALUES(tags), recurring = VALUES(recurring), created_at = VALUES(created_at), updated_at = VALUES(updated_at), team_id = VALUES(team_id), created_by = VALUES(created_by)");
        foreach ($masterData['tasks'] as $t) {
            $stmt->execute([
                ':id' => $t['id'] ?? null,
                ':title' => $t['title'] ?? '',
                ':status' => $t['status'] ?? 'pending',
                ':date' => $t['date'] ?? null,
                ':start_date' => $t['start_date'] ?? null,
                ':end_date' => $t['end_date'] ?? null,
                ':priority' => $t['priority'] ?? null,
                ':due_date' => $t['dueDate'] ?? ($t['due_date'] ?? null),
                ':due_time' => $t['dueTime'] ?? null,
                ':list_id' => $t['listId'] ?? null,
                ':assigned_to' => $t['assigned_to'] ?? null,
                ':tags' => isset($t['tags']) ? json_encode($t['tags']) : null,
                ':recurring' => isset($t['recurring']) ? json_encode($t['recurring']) : null,
                ':created_at' => $t['created_at'] ?? null,
                ':updated_at' => $t['updated_at'] ?? null,
                ':team_id' => $t['team_id'] ?? null,
                ':created_by' => $t['created_by'] ?? null
            ]);
        }
    }

        if (isset($masterData['logs']) && is_array($masterData['logs'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_logs (id, timestamp, user_id, team_id, action, details, ip_address, user_agent)
            VALUES (:id, :timestamp, :user_id, :team_id, :action, :details, :ip_address, :user_agent)
            ON DUPLICATE KEY UPDATE timestamp = VALUES(timestamp), user_id = VALUES(user_id), team_id = VALUES(team_id), action = VALUES(action), details = VALUES(details), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)");
        foreach ($masterData['logs'] as $l) {
            $stmt->execute([
                ':id' => $l['id'] ?? null,
                ':timestamp' => $l['timestamp'] ?? date('c'),
                ':user_id' => $l['user_id'] ?? null,
                ':team_id' => $l['team_id'] ?? null,
                ':action' => $l['action'] ?? '',
                ':details' => isset($l['details']) ? json_encode($l['details']) : null,
                ':ip_address' => $l['ip_address'] ?? null,
                ':user_agent' => $l['user_agent'] ?? null
            ]);
        }
    }

        if (isset($masterData['platforms']) && is_array($masterData['platforms'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_platforms (id, name, code, active, team_id)
            VALUES (:id, :name, :code, :active, :team_id)
            ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), active = VALUES(active), team_id = VALUES(team_id)");
        foreach ($masterData['platforms'] as $p) {
            $stmt->execute([
                ':id' => $p['id'] ?? null,
                ':name' => $p['name'] ?? '',
                ':code' => $p['code'] ?? null,
                ':active' => !empty($p['active']) ? 1 : 0,
                ':team_id' => $p['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['holidays']) && is_array($masterData['holidays'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_holidays (id, name, date, country, team_id)
            VALUES (:id, :name, :date, :country, :team_id)
            ON DUPLICATE KEY UPDATE name = VALUES(name), date = VALUES(date), country = VALUES(country), team_id = VALUES(team_id)");
        foreach ($masterData['holidays'] as $h) {
            $stmt->execute([
                ':id' => $h['id'] ?? null,
                ':name' => $h['name'] ?? '',
                ':date' => $h['date'] ?? null,
                ':country' => $h['country'] ?? null,
                ':team_id' => $h['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['api_keys']) && is_array($masterData['api_keys'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_api_keys (id, key_value, label, role, status, created_at, team_id)
            VALUES (:id, :key_value, :label, :role, :status, :created_at, :team_id)
            ON DUPLICATE KEY UPDATE key_value = VALUES(key_value), label = VALUES(label), role = VALUES(role), status = VALUES(status), created_at = VALUES(created_at), team_id = VALUES(team_id)");
        foreach ($masterData['api_keys'] as $k) {
            $stmt->execute([
                ':id' => $k['id'] ?? null,
                ':key_value' => $k['key'] ?? '',
                ':label' => $k['label'] ?? null,
                ':role' => $k['role'] ?? 'viewer',
                ':status' => $k['status'] ?? 'active',
                ':created_at' => $k['created_at'] ?? null,
                ':team_id' => $k['team_id'] ?? null
            ]);
        }
    }

        if (isset($masterData['invites']) && is_array($masterData['invites'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_invites (token, created_by, team_id, target_role, created_at, expires_at)
            VALUES (:token, :created_by, :team_id, :target_role, :created_at, :expires_at)
            ON DUPLICATE KEY UPDATE created_by = VALUES(created_by), team_id = VALUES(team_id), target_role = VALUES(target_role), created_at = VALUES(created_at), expires_at = VALUES(expires_at)");
        foreach ($masterData['invites'] as $inv) {
            $stmt->execute([
                ':token' => $inv['token'] ?? null,
                ':created_by' => $inv['created_by'] ?? null,
                ':team_id' => $inv['team_id'] ?? null,
                ':target_role' => $inv['target_role'] ?? null,
                ':created_at' => $inv['created_at'] ?? null,
                ':expires_at' => $inv['expires_at'] ?? null
            ]);
        }
    }

        if (isset($masterData['rd_snapshots']) && is_array($masterData['rd_snapshots'])) {
        $stmt = $pdo->prepare("INSERT INTO sb_rd_snapshots (team_id, type, snapshot, updated_at)
            VALUES (:team_id, :type, :snapshot, :updated_at)
            ON DUPLICATE KEY UPDATE snapshot = VALUES(snapshot), updated_at = VALUES(updated_at)");
        foreach ($masterData['rd_snapshots'] as $snap) {
            if (!is_array($snap)) {
                continue;
            }
            $stmt->execute([
                ':team_id' => $snap['team_id'] ?? null,
                ':type' => $snap['type'] ?? '',
                ':snapshot' => json_encode($snap['snapshot'] ?? []),
                ':updated_at' => $snap['updated_at'] ?? null
            ]);
        }
        }
    } catch (Throwable $e) {
        error_log('sb_sync_master_brain_to_sql error: ' . $e->getMessage());
    }
}
