<?php
class CardBuilderFunctions {
    private $dataFile;
    private $data;
    private $projectName = 'default';

    public function __construct($customProject = null) {
        // Determine Project Context
        if ($customProject) {
            $this->projectName = $customProject;
        } elseif (isset($_REQUEST['project'])) {
            $this->projectName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['project']);
        } elseif (isset($_SESSION['current_project'])) {
            $this->projectName = $_SESSION['current_project'];
        }

        // Always use data.json for centralized storage
        $this->dataFile = __DIR__ . '/data.json';
        
        if (!$this->projectName) {
            $this->projectName = 'default';
        }

        $this->loadData();
    }

    public function getCurrentProject() {
        return $this->projectName;
    }
    
    // List all available projects
    // --- Unified Global Data (For Admin Dashboard) ---
    public function getGlobalSpecifications() {
        $files = glob(__DIR__ . '/data_*.json');
        $allSpecs = [];
        
        // Always include default
        if(!in_array(__DIR__ . '/data.json', $files)) {
            $files[] = __DIR__ . '/data.json';
        }

        foreach ($files as $file) {
            $name = str_replace(['data_', '.json'], '', basename($file));
            if ($name === 'data') $name = 'default';
            
            $json = json_decode(file_get_contents($file), true);
            if(isset($json['specifications']) && is_array($json['specifications'])) {
                foreach($json['specifications'] as $spec) {
                    $spec['_source_project'] = $name; // Tag source
                    $allSpecs[] = $spec;
                }
            }
        }
        
        // Sort by timestamp desc
        usort($allSpecs, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        return $allSpecs;
    }

    public function getGlobalAnalytics() {
        $files = glob(__DIR__ . '/data_*.json');
        // Include default
        if(!in_array(__DIR__ . '/data.json', $files)) $files[] = __DIR__ . '/data.json';

        $analytics = [
            'totalUsers' => 0,
            'totalSpecs' => 0,
            'revenue' => 0,
            'projects' => []
        ];
        
        foreach ($files as $file) {
            $name = str_replace(['data_', '.json'], '', basename($file));
            if ($name === 'data') $name = 'default';
            
            $json = json_decode(file_get_contents($file), true);
            
            // Stats for this project
            $pRevenue = 0;
            $pSpecs = 0;
            
            if(isset($json['specifications'])) {
                $pSpecs = count($json['specifications']);
                foreach($json['specifications'] as $s) {
                    $pRevenue += floatval($s['totalPrice'] ?? 0);
                }
            }
            
            $analytics['totalSpecs'] += $pSpecs;
            $analytics['revenue'] += $pRevenue;
            
            // Users are shared/copied, so maybe avg? Or just take from current?
            // Actually users are not synced, they are copied on create.
            // Let's just Count unique users across all?
            // For now, let's just take raw count from current context or sum?
            // Users are actually "Admin Users". Customers are Guest/transient.
            // Let's count unique customer names/emails from specs?
            // For simplicity, just sum 'users' count from default file as it's the master
            if($name === 'default' && isset($json['users'])) {
                 $analytics['totalUsers'] = count($json['users']);
            }

            $analytics['projects'][$name] = [
                'specs' => $pSpecs,
                'revenue' => $pRevenue
            ];
        }
        
        return $analytics;
    }

    public function getProjects() {
        if (!isset($this->data['profiles'])) return ['default'];
        return array_keys($this->data['profiles']);
    }
    
    // Create new project
    public function createProject($newName) {
        $newName = preg_replace('/[^a-zA-Z0-9_-]/', '', $newName);
        if (!$newName || $newName === 'default') return false;
        
        if (isset($this->data['profiles'][$newName])) return false; 
        
        $this->data['profiles'][$newName] = $this->getDefaultData();
        // Label defaults to project name
        $this->data['profiles'][$newName]['rootLabel'] = $newName;
        
        $this->saveData();
        return true;
    }

    public function renameProject($oldName, $newName) {
        $oldName = preg_replace('/[^a-zA-Z0-9_-]/', '', $oldName);
        $newName = preg_replace('/[^a-zA-Z0-9_-]/', '', $newName);
        
        if ($oldName === 'default' || !$newName || !isset($this->data['profiles'][$oldName]) || isset($this->data['profiles'][$newName])) {
            return false;
        }

        // Transfer profile data
        $this->data['profiles'][$newName] = $this->data['profiles'][$oldName];
        unset($this->data['profiles'][$oldName]);

        // Update existing specifications belonging to this project
        if (isset($this->data['specifications'])) {
            foreach ($this->data['specifications'] as &$spec) {
                if (($spec['_source_project'] ?? 'default') === $oldName) {
                    $spec['_source_project'] = $newName;
                }
            }
        }

        $this->saveData();
        if ($this->projectName === $oldName) {
            $this->projectName = $newName;
        }
        return true;
    }

    public function deleteProject($name) {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        if ($name === 'default' || !isset($this->data['profiles'][$name])) {
            return false;
        }

        unset($this->data['profiles'][$name]);
        $this->saveData();
        return true;
    }

    private function loadData() {
        if (file_exists($this->dataFile)) {
            $json = file_get_contents($this->dataFile);
            $this->data = json_decode($json, true);
            if (!is_array($this->data)) {
                $this->data = $this->getDefaultData();
                $this->data['profiles'] = ['default' => $this->getDefaultProfileData()];
            }

            // Ensure profiles section exists
            if (!isset($this->data['profiles'])) {
                $this->data['profiles'] = [];
            }
            
            // Clean up root level config if it's already in profiles (Migration cleanup)
            if (isset($this->data['config']) && isset($this->data['profiles']['default'])) {
                unset($this->data['config']);
                unset($this->data['prices']);
                unset($this->data['rootRules']);
                unset($this->data['steps']);
                unset($this->data['rootLabel']);
                unset($this->data['activeTab']);
                unset($this->data['selectedRoot']);
            }
            
            // If default profile is missing, create it
            if (!isset($this->data['profiles']['default'])) {
                 $this->data['profiles']['default'] = $this->getDefaultProfileData();
            }
        } else {
            $this->data = $this->getDefaultData();
            $this->data['profiles'] = ['default' => $this->getDefaultProfileData()];
            $this->saveData();
        }
    }

    private function saveData() {
        // Ensure all profiles have objects for prices and rootRules before saving
        if (isset($this->data['profiles'])) {
            foreach ($this->data['profiles'] as $name => &$pData) {
                if (isset($pData['prices']) && is_array($pData['prices']) && empty($pData['prices'])) {
                    $pData['prices'] = (object)[];
                }
                if (isset($pData['rootRules']) && is_array($pData['rootRules']) && empty($pData['rootRules'])) {
                    $pData['rootRules'] = (object)[];
                }
            }
        }
        
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->atomicWriteFile($this->dataFile, $json);
    }

    private function atomicWriteFile($filepath, $contents) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = $filepath . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            return false;
        }
        if (!@rename($tmp, $filepath)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    private function getDefaultData() {
        return [
            "users" => [],
            "materials" => [],
            "specifications" => [], 
            "analytics" => [],
            "compatibility" => [],
            "ui_config" => []
        ];
    }

    // --- User Management ---
    public function authenticateUser($username, $password) {
        if (isset($this->data['users'][$username])) {
            $user = $this->data['users'][$username];
            $stored = $user['password'] ?? '';
            $isHash = is_string($stored) && str_starts_with($stored, '$2y$');

            $ok = false;
            if ($isHash) {
                $ok = password_verify($password, $stored);
            } else {
                // Backwards-compatible legacy plaintext support
                $ok = hash_equals((string)$stored, (string)$password);
                if ($ok) {
                    // Upgrade on successful login
                    $this->data['users'][$username]['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
            }

            if ($ok) {
                // Update last login
                $this->data['users'][$username]['profile']['lastLogin'] = date('Y-m-d H:i:s');
                $this->saveData();
                return $this->data['users'][$username];
            }
        }
        return false;
    }

    public function createUser($username, $password, $email, $name, $role = 'user') {
        if (isset($this->data['users'][$username])) {
            return false; // User already exists
        }

        $this->data['users'][$username] = [
            'id' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'profile' => [
                'name' => $name,
                'email' => $email,
                'created' => date('Y-m-d H:i:s'),
                'lastLogin' => null
            ]
        ];

        $this->saveData();
        return true;
    }

    public function getUserProfile($username) {
        return $this->data['users'][$username] ?? null;
    }

    public function getAllUsers() {
        return $this->data['users'] ?? [];
    }

    // --- Material Management ---
    public function getMaterials($category = null) {
        if ($category && isset($this->data['materials'][$category])) {
            return $this->data['materials'][$category];
        }
        return $this->data['materials'];
    }

    // ADMIN: Update existing material
    public function updateMaterial($category, $id, $materialData) {
        if (!isset($this->data['materials'][$category])) {
            return false;
        }

        $index = array_search($id, array_column($this->data['materials'][$category], 'id'));
        if ($index !== false) {
             // Preserve ID if not in update data, though it should be handled carefully
            $current = $this->data['materials'][$category][$index];
            $this->data['materials'][$category][$index] = array_merge($current, $materialData);
            $this->saveData();
            return true;
        }
        return false;
    }

    // ADMIN: Add new material
    public function addMaterial($category, $materialData) {
        if (!isset($this->data['materials'][$category])) {
             // Create category if it doesn't exist? Or enforce strict categories?
             // For now, let's create it.
             $this->data['materials'][$category] = [];
        }

        // Generate ID if not provided
        if (!isset($materialData['id'])) {
             $materialData['id'] = uniqid($category . '_');
        }
        
        $this->data['materials'][$category][] = $materialData;
        $this->saveData();
        return $materialData['id'];
    }

    // ADMIN: Delete material
    public function deleteMaterial($category, $id) {
        if (!isset($this->data['materials'][$category])) {
            return false;
        }

        $index = array_search($id, array_column($this->data['materials'][$category], 'id'));
        if ($index !== false) {
            array_splice($this->data['materials'][$category], $index, 1);
            $this->saveData();
            return true;
        }
        return false;
    }

    // --- Specification Management ---
    public function saveSpecification($userId, $selection, $totalPrice, $customerDetails = []) {
        if (!isset($this->data['specifications'][$userId])) {
            $this->data['specifications'][$userId] = [];
        }

        $specification = [
            'id' => uniqid('spec_'),
            'timestamp' => date('Y-m-d\TH:i:s'),
            'selection' => $selection,
            'totalPrice' => $totalPrice,
            'customer' => $customerDetails, // Added customer details
            'status' => 'pending' // pending, processing, completed, cancelled
        ];

        $this->data['specifications'][$userId][] = $specification;

        // Update analytics
        $this->updateAnalytics($selection, $totalPrice);

        $this->saveData();
        return $specification['id'];
    }

    // ADMIN: Get all specifications (for dashboard)
    public function getAllSpecifications() {
        $allSpecs = [];
        if (isset($this->data['specifications'])) {
            foreach ($this->data['specifications'] as $userId => $specs) {
                foreach ($specs as $spec) {
                    $spec['user_id'] = $userId; // Attach user ID for context
                    $allSpecs[] = $spec;
                }
            }
        }
        // Sort by timestamp desc
        usort($allSpecs, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        return $allSpecs;
    }

    public function getUserSpecifications($userId) {
        return $this->data['specifications'][$userId] ?? [];
    }

    public function updateSpecificationStatus($userId, $specId, $status) {
        if (!isset($this->data['specifications'][$userId])) {
            return false;
        }

        $index = array_search($specId, array_column($this->data['specifications'][$userId], 'id'));
        if ($index !== false) {
            $this->data['specifications'][$userId][$index]['status'] = $status;
            $this->saveData();
            return true;
        }
        return false;
    }

    // --- Compatibility ---
    public function checkCompatibility($fromCategory, $fromValue, $toCategory, $toValue) {
        if (!isset($this->data['compatibility']['rules'])) {
            return true; // No rules defined, allow all
        }

        foreach ($this->data['compatibility']['rules'] as $rule) {
            if ($rule['from'] === $fromCategory && $rule['to'] === $toCategory) {
                if ($rule['allowed'] === 'all') {
                    return true;
                }

                if (isset($rule['allowed'][$fromValue]) &&
                    in_array($toValue, $rule['allowed'][$fromValue])) {
                    return true;
                }
                return false;
            }
        }

        return true; // No specific rule found, allow
    }

    public function getCompatibleOptions($fromCategory, $fromValue, $toCategory) {
        $allOptions = $this->getMaterials($toCategory);
        $compatible = [];

        foreach ($allOptions as $option) {
            if ($this->checkCompatibility($fromCategory, $fromValue, $toCategory, $option['id'])) {
                $compatible[] = $option;
            }
        }

        return $compatible;
    }

    // --- Analytics ---
    private function updateAnalytics($selection, $totalPrice) {
        if (!isset($this->data['analytics'])) {
            $this->data['analytics'] = [
                'totalUsers' => 0,
                'totalSpecifications' => 0,
                'popularMaterials' => [],
                'revenue' => 0
            ];
        }
        
        // Ensure keys exist
        if (!isset($this->data['analytics']['totalSpecifications'])) $this->data['analytics']['totalSpecifications'] = 0;
        if (!isset($this->data['analytics']['revenue'])) $this->data['analytics']['revenue'] = 0;
        if (!isset($this->data['analytics']['popularMaterials'])) $this->data['analytics']['popularMaterials'] = [];

        $this->data['analytics']['totalSpecifications']++;
        $this->data['analytics']['revenue'] += $totalPrice;

        // Iterate through all selected items to track popularity
        foreach ($selection as $category => $itemId) {
            if ($itemId) {
                if (!isset($this->data['analytics']['popularMaterials'][$itemId])) {
                    $this->data['analytics']['popularMaterials'][$itemId] = 0;
                }
                $this->data['analytics']['popularMaterials'][$itemId]++;
            }
        }
    }

    public function getAnalytics() {
        // Calculate dynamic stats if needed, or return stored
        // For real-time consistency, we might want to recalculate some on the fly if file is edited manually?
        // But for now, trusting the stored analytics is faster.
        $analytics = $this->data['analytics'] ?? [];
        $analytics['totalUsers'] = count($this->data['users'] ?? []);
        return $analytics;
    }

    // --- Real-time Updates ---
    public function getRealTimeData() {
        $recentSpecs = array_slice($this->getGlobalSpecifications(), 0, 10);
        $events = [];
        
        foreach ($recentSpecs as $spec) {
            $events[] = [
                'type' => 'order',
                'message' => "New Order: Spec #{$spec['id']} for " . ($spec['customer']['name'] ?? 'Guest'),
                'timestamp' => $spec['timestamp'],
                'data' => $spec
            ];
        }

        // Add user registered events
        $users = array_slice(array_reverse($this->data['users'] ?? []), 0, 5);
        foreach ($users as $username => $user) {
            $events[] = [
                'type' => 'user',
                'message' => "New User: {$username} ({$user['profile']['name']})",
                'timestamp' => $user['profile']['created'] ?? null,
                'data' => $user
            ];
        }

        // Sort events by timestamp desc
        usort($events, function($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return [
            'timestamp' => date('Y-m-d\TH:i:s'),
            'materials' => $this->getMaterials(),
            'analytics' => $this->getAnalytics(),
            'recent_specs' => array_slice($recentSpecs, 0, 5),
            'events' => array_slice($events, 0, 10),
            'ui_config' => $this->getUiConfig()
        ];
    }
    
    // --- UI Config Management ---
    public function getUiConfig() {
        $p = $this->projectName;
        if (!isset($this->data['profiles'][$p])) {
            return $this->getDefaultProfileData();
        }
        
        $profileData = $this->data['profiles'][$p];
        
        // Normalize: Ensure prices and rootRules are objects if empty (JSON compatibility)
        if (isset($profileData['prices']) && (is_array($profileData['prices']) && empty($profileData['prices']))) {
            $profileData['prices'] = (object)[];
        }
        if (isset($profileData['rootRules']) && (is_array($profileData['rootRules']) && empty($profileData['rootRules']))) {
            $profileData['rootRules'] = (object)[];
        }
        if (!isset($profileData['config'])) $profileData['config'] = [];
        if (!isset($profileData['steps'])) $profileData['steps'] = [];
        
        return $profileData;
    }

    public function saveUiConfig($config) {
        $p = $this->projectName;
        if (!isset($this->data['profiles'][$p])) {
            $this->data['profiles'][$p] = [];
        }

        // Merge config onto profile data
        foreach ($config as $key => $value) {
            $this->data['profiles'][$p][$key] = $value;
        }

        // Clean up legacy keys if they somehow made it in
        unset($this->data['ui_config']);
        
        $this->saveData();
        return true;
    }

    private function getDefaultProfileData() {
        return [
            'config' => [],
            'prices' => (object)[],
            'rootRules' => (object)[],
            'steps' => [],
            'rootLabel' => 'Material Foundation',
            'dimensions' => [
                'width' => 8.5,
                'height' => 5.5,
                'unit' => 'cm'
            ],
            'assets' => [
                'previewImage' => null,
                'previewVideo' => null
            ]
        ];
    }

    // --- Utility ---
    public function calculatePrice($selection) {
        $total = 0;
        
        // 1. Try to check new UI Config Prices first
        $uiConfig = $this->getUiConfig();
        if (isset($uiConfig['prices'])) {
             $prices = $uiConfig['prices'];
             foreach ($selection as $category => $value) {
                 if ($value) {
                     // Category in selection matches price keys (specialPaper, paper, lamination, effect, corner)
                     if (isset($prices[$category]) && isset($prices[$category][$value])) {
                         $total += (float)$prices[$category][$value];
                     } else {
                         // Default fallback if item exists but price missing? 
                         // Or if item is not in config?
                     }
                 }
             }
             return $total;
        }

        // 2. Fallback to old Legacy Materials logic
        $materials = $this->getMaterials();
        foreach ($selection as $category => $value) {
            if (isset($materials[$category])) {
                foreach ($materials[$category] as $item) {
                    if ($item['id'] === $value) {
                        $total += $item['price'];
                        break;
                    }
                }
            }
        }

        return $total;
    }
    // --- OTP Management ---
    public function generateOTP($username) {
        $otpFile = __DIR__ . '/otp.json';
        $otps = [];
        if (file_exists($otpFile)) {
            $otps = json_decode(file_get_contents($otpFile), true);
            if(!is_array($otps)) $otps = [];
        }

        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $otps[$username] = [
            'code' => $code,
            'expires' => time() + (5 * 60) // 5 minutes
        ];

        $this->atomicWriteFile($otpFile, json_encode($otps, JSON_PRETTY_PRINT));
        return $code; // In real app, send via Email/SMS
    }

    public function verifyOTP($username, $code) {
        $otpFile = __DIR__ . '/otp.json';
        if (!file_exists($otpFile)) return false;

        $otps = json_decode(file_get_contents($otpFile), true);
        if (isset($otps[$username])) {
            $data = $otps[$username];
            if ($data['code'] === $code && time() < $data['expires']) {
                // Determine valid, maybe remove used OTP?
                unset($otps[$username]);
                $this->atomicWriteFile($otpFile, json_encode($otps, JSON_PRETTY_PRINT));
                return true;
            }
        }
        return false;
    }

    // --- User Security ---
    public function createUserSecure($username, $password, $email, $name, $question, $answer) {
        if (isset($this->data['users'][$username])) return false;

        $this->data['users'][$username] = [
            'id' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
            'security_question' => $question,
            'security_answer' => strtolower(trim($answer)), // Simple normalization
            'profile' => [
                'name' => $name,
                'email' => $email,
                'created' => date('Y-m-d H:i:s'),
                'lastLogin' => null
            ]
        ];
        $this->saveData();
        return true;
    }
    
    public function verifySecurityAnswer($username, $answer) {
        if (isset($this->data['users'][$username])) {
             $stored = $this->data['users'][$username]['security_answer'] ?? '';
             return $stored === strtolower(trim($answer));
        }
        return false;
    }

    public function getSecurityQuestion($username) {
        return $this->data['users'][$username]['security_question'] ?? null;
    }

    public function resetPassword($username, $newPassword) {
        if (isset($this->data['users'][$username])) {
            $this->data['users'][$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->saveData();
            return true;
        }
        return false;
    }
}

// --- API Handling ---
// Only run if this file is accessed directly, NOT when included
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Use hardened session bootstrap from parent functions.php
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../functions.php';
        sb_session_start_secure();
    }
    
    // Basic session check if needed, but for now open API as per request for "realtime"
    // In prod, check CSRF/Auth.
    
    $cardBuilder = new CardBuilderFunctions();
    $response = ['success' => false, 'data' => null, 'message' => ''];

    try {
        // CSRF for authenticated/admin actions (browser sessions)
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        $needCsrf = !empty($_SESSION['user_id']); // only enforce once logged in
        if ($needCsrf) {
            $sess = $_SESSION['csrf_token'] ?? '';
            if (!is_string($sess) || $sess === '' || !is_string($csrfToken) || $csrfToken === '' || !hash_equals($sess, $csrfToken)) {
                $response['message'] = 'CSRF validation failed';
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }

        // Admin-only actions must require an authenticated admin session
        $adminActions = [
            'get_all_specs',
            'update_spec_status',
            'add_material',
            'update_material',
            'delete_material',
            'save_ui_config',
            'create_project',
            'rename_project',
            'delete_project'
        ];
        if (in_array($_POST['action'], $adminActions, true)) {
            $role = $_SESSION['role'] ?? '';
            if ($role !== 'admin') {
                $response['message'] = 'Unauthorized';
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }

        switch ($_POST['action']) {
            case 'login':
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $user = $cardBuilder->authenticateUser($username, $password);
                if ($user) {
                    // New session id after login to reduce fixation risk
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $response['success'] = true;
                    $response['data'] = ['redirect' => $user['role'] === 'admin' ? 'admin.php' : 'index.php']; // Force index.php for users
                } else {
                    $response['message'] = 'Invalid credentials';
                }
                break;
            
            case 'register':
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $email = $_POST['email'] ?? '';
                $name = $_POST['name'] ?? '';
                if (strlen((string)$password) < 10) {
                    $response['message'] = 'Password must be at least 10 characters';
                    break;
                }
                if ($cardBuilder->createUser($username, $password, $email, $name)) {
                    $response['success'] = true;
                    $response['message'] = 'User created successfully';
                } else {
                     $response['message'] = 'Username already exists';
                }
                break;

            case 'get_materials':
                $category = $_POST['category'] ?? null;
                $response['success'] = true;
                $response['data'] = $cardBuilder->getMaterials($category);
                break;

            case 'save_specification':
                $userId = $_POST['user_id'] ?? 'guest';
                $selection = json_decode($_POST['selection'], true);
                $customer = json_decode($_POST['customer'], true) ?? []; // Get customer data
                $totalPrice = $cardBuilder->calculatePrice($selection);

                $specId = $cardBuilder->saveSpecification($userId, $selection, $totalPrice, $customer);
                $response['success'] = true;
                $response['data'] = ['specId' => $specId, 'totalPrice' => $totalPrice];
                break;

            case 'get_user_specs':
                $userId = $_POST['user_id'] ?? 'guest';
                $response['success'] = true;
                $response['data'] = $cardBuilder->getUserSpecifications($userId);
                break;

            case 'upload_texture':
                if (isset($_FILES['texture']) && $_FILES['texture']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/assets/textures/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                    if (($_FILES['texture']['size'] ?? 0) > 5 * 1024 * 1024) {
                        $response['error'] = 'File too large (max 5MB).';
                        break;
                    }

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['texture']['tmp_name']);
                    finfo_close($finfo);
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];
                    if (!isset($allowed[$mimeType])) {
                        $response['error'] = 'Invalid image type (JPG/PNG/WEBP only).';
                        break;
                    }
                    
                    $ext = $allowed[$mimeType];
                    $filename = 'tex_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['texture']['tmp_name'], $filepath)) {
                        $response['success'] = true;
                        $response['data'] = ['url' => 'assets/textures/' . $filename];
                    } else {
                        $response['error'] = 'Failed to move uploaded file.';
                    }
                } else {
                    $response['error'] = 'No file uploaded or upload error.';
                }
                break;

            case 'register_secure':
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $email = $_POST['email'] ?? '';
                $name = $_POST['name'] ?? '';
                $question = $_POST['question'] ?? '';
                $answer = $_POST['answer'] ?? '';
                if (strlen((string)$password) < 10) {
                    $response['message'] = 'Password must be at least 10 characters';
                    break;
                }
                
                // Server-side Robot Check
                if (($_POST['captcha'] ?? '') != ($_SESSION['captcha'] ?? 'FAIL')) {
                    $response['message'] = 'Incorrect Math Verification';
                    break;
                }

                if ($cardBuilder->createUserSecure($username, $password, $email, $name, $question, $answer)) {
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Username already exists';
                }
                break;

            case 'get_security_question':
                $username = $_POST['username'] ?? '';
                $q = $cardBuilder->getSecurityQuestion($username);
                if($q) {
                    $response['success'] = true;
                    $response['data'] = ['question' => $q];
                } else {
                    $response['message'] = 'User not found';
                }
                break;

            case 'reset_password':
                $username = $_POST['username'] ?? '';
                $answer = $_POST['answer'] ?? '';
                $newPass = $_POST['new_password'] ?? '';
                
                if($cardBuilder->verifySecurityAnswer($username, $answer)) {
                    $cardBuilder->resetPassword($username, $newPass);
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Incorrect Security Answer';
                }
                break;

            case 'get_all_specs': // Admin only
                $response['success'] = true;
                // Use Global for Admin List
                $response['data'] = $cardBuilder->getGlobalSpecifications();
                break;
            
            case 'update_spec_status': // Admin only
                $userId = $_POST['user_id'];
                $specId = $_POST['spec_id'];
                $status = $_POST['status'];
                // Update logic needs to find WHICH file the spec belongs to? 
                // Currently `updateSpecificationStatus` loads `$this->data`.
                // If we want to update a spec from "BusinessCards" while in "Wedding" context,
                // we'd need to switch context or pass project name.
                // For now, let's assume the admin switches to the project to Edit?
                // UPDATE: User wants "Unified". To update status globally, we need `project` param.
                // Let's rely on the frontend passing 'project' if we add it to the table data.
                
                // If spec has _source_project, we can re-instantiate CardBuilder?
                // Or easier: Just load that file, update, save.
                // But `updateSpecificationStatus` is bound to `$this->data`.
                // Let's stick to: "View Global, but click 'Switch' to Edit"?
                // Or better: The `__construct` handles context.
                // If we pass `project` in POST, the instance is ALREADY correct!
                
                if ($cardBuilder->updateSpecificationStatus($userId, $specId, $status)) {
                    $response['success'] = true;
                }
                break;

            case 'get_compatible_options':
                $fromCategory = $_POST['from_category'];
                $fromValue = $_POST['from_value'];
                $toCategory = $_POST['to_category'];

                $options = $cardBuilder->getCompatibleOptions($fromCategory, $fromValue, $toCategory);
                $response['success'] = true;
                $response['data'] = $options;
                break;

            case 'get_analytics':
                $response['success'] = true;
                $response['data'] = $cardBuilder->getGlobalAnalytics();
                break;

            case 'get_realtime_data':
                $response['success'] = true;
                $response['data'] = $cardBuilder->getRealTimeData();
                break;
            
            // ADMIN: Material Management
            case 'add_material':
                $category = $_POST['category'];
                $materialData = json_decode($_POST['material_data'], true);
                if ($id = $cardBuilder->addMaterial($category, $materialData)) {
                    $response['success'] = true;
                    $response['data'] = ['id' => $id];
                }
                break;
                
            case 'update_material':
                $category = $_POST['category'];
                $id = $_POST['id'];
                $materialData = json_decode($_POST['material_data'], true);
                if ($cardBuilder->updateMaterial($category, $id, $materialData)) {
                    $response['success'] = true;
                }
                break;
                
            case 'delete_material':
                $category = $_POST['category'];
                $id = $_POST['id'];
                if ($cardBuilder->deleteMaterial($category, $id)) {
                    $response['success'] = true;
                }
                break;

            // --- UI Config ---
            case 'get_ui_config':
                $response['success'] = true;
                $response['data'] = $cardBuilder->getUiConfig();
                break;
                
            case 'save_ui_config':
                $config = json_decode($_POST['config'], true);
                if ($config) {
                    if ($cardBuilder->saveUiConfig($config)) {
                        $response['success'] = true;
                    }
                } else {
                    $response['error'] = 'Invalid configuration data';
                }
                break;

            // --- Multi-Project API ---
            case 'get_projects':
                $response['success'] = true;
                $response['data'] = $cardBuilder->getProjects();
                $response['current'] = $cardBuilder->getCurrentProject();
                break;
                
            case 'create_project':
                $name = $_POST['name'] ?? '';
                if ($cardBuilder->createProject($name)) {
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Project already exists or invalid name';
                }
                break;

            case 'rename_project':
                $old = $_POST['old_name'] ?? '';
                $new = $_POST['new_name'] ?? '';
                if ($cardBuilder->renameProject($old, $new)) {
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Failed to rename';
                }
                break;

            case 'delete_project':
                $name = $_POST['name'] ?? '';
                if ($cardBuilder->deleteProject($name)) {
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Failed to delete';
                }
                break;

        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
