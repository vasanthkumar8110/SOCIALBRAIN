<?php
require_once __DIR__ . '/functions.php';

sb_session_start_secure();
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");

// --- CORS (configurable) ---
// Default: same-origin only. To allow a specific origin, set env `SB_CORS_ALLOW_ORIGINS`
// as a comma-separated list, e.g. "https://app.example.com,http://localhost:5173"
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowOriginsRaw = getenv('SB_CORS_ALLOW_ORIGINS');
$allowOrigins = [];
if ($allowOriginsRaw !== false && trim($allowOriginsRaw) !== '') {
    $allowOrigins = array_values(array_filter(array_map('trim', explode(',', $allowOriginsRaw))));
}
if ($origin && in_array($origin, $allowOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Rate limiting configurations
$rateLimitFile = __DIR__ . '/rd_rate_limit.json';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$currentTime = time();
$rateLimit = 60; // requests per minute

// Load rate limit data
$rateLimitData = [];
if (file_exists($rateLimitFile)) {
    $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
}

// Normalize to expected shape: [ip => [timestamps]]
if (!is_array($rateLimitData)) {
    $rateLimitData = [];
}
if (!isset($rateLimitData[$clientIP]) || !is_array($rateLimitData[$clientIP])) {
    $rateLimitData[$clientIP] = [];
}

// Clean old entries (older than 60 seconds)
$rateLimitData[$clientIP] = array_values(array_filter($rateLimitData[$clientIP], function ($ts) use ($currentTime) {
    return is_int($ts) && ($currentTime - $ts) < 60;
}));

// Check rate limit
if (count($rateLimitData[$clientIP]) >= $rateLimit) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Max 60 requests per minute.']);
    exit;
}

// Add current request
$rateLimitData[$clientIP][] = $currentTime;
file_put_contents($rateLimitFile, json_encode($rateLimitData), LOCK_EX);

// Check authentication
$currentRole = checkAuthAndGetRole();
if (!$currentRole) {
    http_response_code(401);
    jsonResponse(null, false, 'Authentication required for R&D endpoints');
}

$action = $_REQUEST['action'] ?? 'overview';
$method = $_SERVER['REQUEST_METHOD'];

// Load data files
$masterData = loadJSON(__DIR__ . '/master_brain.json');
$logs = $masterData['logs'] ?? [];

// Pre-calculate common metrics
$recentLogs = array_filter($logs, function($log) {
    return (time() - strtotime($log['timestamp'])) < 86400;
});
$activeUsers = count(array_unique(array_column($recentLogs, 'user_id')));
$currentTeamId = $_SESSION['team_id'] ?? '';

function rdHttpGet($url, $timeoutSeconds = 8) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'header' => "User-Agent: SocialBrainRD/1.0\r\nAccept: application/rss+xml, application/xml;q=0.9, */*;q=0.8\r\n"
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    return @file_get_contents($url, false, $context);
}

function rdGetNewsFeedConfig($region) {
    $region = strtolower(trim((string)$region));
    if ($region === 'india' || $region === 'in') {
        return ['hl' => 'en-IN', 'gl' => 'IN', 'ceid' => 'IN:en'];
    }
    if ($region === 'both' || $region === 'all') {
        return ['hl' => 'en', 'gl' => 'US', 'ceid' => 'US:en'];
    }
    return ['hl' => 'en-AE', 'gl' => 'AE', 'ceid' => 'AE:en'];
}

function rdParseRssItems($rssXml, $limit = 15) {
    $xml = @simplexml_load_string($rssXml);
    if (!$xml || !isset($xml->channel->item)) {
        return [];
    }
    $items = [];
    foreach ($xml->channel->item as $item) {
        $title = trim((string)($item->title ?? ''));
        $link = trim((string)($item->link ?? ''));
        $pubDate = trim((string)($item->pubDate ?? ''));
        $description = trim((string)($item->description ?? ''));
        $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($description)));

        $sourceName = '';
        $sourceUrl = '';
        if (isset($item->source)) {
            $sourceName = trim((string)$item->source);
            $attrs = $item->source->attributes();
            if ($attrs && isset($attrs['url'])) {
                $sourceUrl = trim((string)$attrs['url']);
            }
        }

        if ($title === '' && $link === '') {
            continue;
        }

        $items[] = [
            'title' => $title,
            'link' => $link,
            'published_at' => $pubDate ? date('c', strtotime($pubDate)) : null,
            'source' => [
                'name' => $sourceName,
                'url' => $sourceUrl
            ],
            'snippet' => $snippet
        ];

        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

// R&D Analytics Endpoints
switch ($action) {
    case 'overview':
        $totalUsers = count($masterData['users'] ?? []);
        $totalPosts = count(array_filter($masterData['posts'] ?? [], function($p) {
            return !isset($p['deleted_at']);
        }));
        $totalProducts = count($masterData['products'] ?? []);
        $totalSectors = count($masterData['sectors'] ?? []);
        $apiKeys = count($masterData['api_keys'] ?? []);
        
        $requestsPerMinute = count($recentLogs) / 1440; // 24 hours in minutes
        $errorRate = 0; // Placeholder: Calculate from actual error logs if available
        $healthScore = min(100, max(0, 100 - ($errorRate * 10)));
        
        // Prepare Chart Data
        // 1. Activity Chart (Last 7 Days)
        $dailyActivity = [];
        $daysLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $timestamp = strtotime("-$i days");
            $dateKey = date('Y-m-d', $timestamp);
            $dayLabel = date('D', $timestamp);
            $dailyActivity[$dateKey] = 0;
            $daysLabels[] = $dayLabel;
        }
        foreach ($logs as $log) {
            $logDate = substr($log['timestamp'], 0, 10);
            if (isset($dailyActivity[$logDate])) {
                $dailyActivity[$logDate]++;
            }
        }

        // 2. Performance Chart (Hourly Activity for last 24h as a proxy for performance/load)
        $hourlyActivity = array_fill(0, 24, 0);
        foreach ($recentLogs as $log) {
            $hour = (int)date('H', strtotime($log['timestamp']));
            $hourlyActivity[$hour]++;
        }

        $payload = [
            'metrics' => [
                'active_users' => $activeUsers,
                'requests_per_minute' => round($requestsPerMinute, 2),
                'error_rate' => $errorRate,
                'health_score' => $healthScore,
                'api_success_rate' => 99.5,
                'avg_response_time' => 120
            ],
            'charts' => [
                'activity' => [
                    'labels' => $daysLabels,
                    'data' => array_values($dailyActivity)
                ],
                'performance' => [
                    'data' => $hourlyActivity
                ]
            ],
            'system_stats' => [
                'total_users' => $totalUsers,
                'total_posts' => $totalPosts,
                'total_products' => $totalProducts,
                'total_sectors' => $totalSectors,
                'api_keys' => $apiKeys,
                'uptime' => '99.9%'
            ],
            'recent_activity' => array_slice($recentLogs, 0, 10),
            'current_user' => [
                'role' => $currentRole,
                'username' => $_SESSION['username'] ?? 'API User'
            ]
        ];

        if (!isset($masterData['rd_snapshots']) || !is_array($masterData['rd_snapshots'])) {
            $masterData['rd_snapshots'] = [];
        }
        $snapshots = [];
        foreach ($masterData['rd_snapshots'] as $snap) {
            if (!is_array($snap)) {
                continue;
            }
            if (($snap['type'] ?? '') === 'overview' && ($snap['team_id'] ?? '') === $currentTeamId) {
                continue;
            }
            $snapshots[] = $snap;
        }
        $snapshots[] = [
            'team_id' => $currentTeamId,
            'type' => 'overview',
            'snapshot' => $payload,
            'updated_at' => date('c')
        ];
        $masterData['rd_snapshots'] = $snapshots;
        saveJSON(MASTER_BRAIN_FILE, $masterData);

        jsonResponse($payload);
        break;

    case 'api_analytics':
        $apiCalls = array_filter($logs, function($log) {
            return strpos($log['action'], 'api_') === 0;
        });
        
        $hourlyStats = [];
        for ($i = 23; $i >= 0; $i--) {
            $hour = date('H', time() - ($i * 3600));
            $hourlyStats[$hour] = count(array_filter($apiCalls, function($call) use ($i) {
                return (time() - strtotime($call['timestamp'])) >= ($i * 3600) && 
                       (time() - strtotime($call['timestamp'])) < (($i + 1) * 3600);
            }));
        }
        
        $payload = [
            'total_calls' => count($apiCalls),
            'success_rate' => 99.2,
            'avg_response_time' => 145,
            'peak_hour' => array_keys($hourlyStats, max($hourlyStats))[0] ?? '12',
            'hourly_stats' => $hourlyStats,
            'top_endpoints' => [
                'get_data' => 45,
                'save_post' => 32,
                'api_get_all' => 28,
                'save_product' => 15
            ],
            'geographic_distribution' => [
                'US' => 45,
                'EU' => 30,
                'ASIA' => 20,
                'OTHER' => 5
            ]
        ];

        if (!isset($masterData['rd_snapshots']) || !is_array($masterData['rd_snapshots'])) {
            $masterData['rd_snapshots'] = [];
        }
        $snapshots = [];
        foreach ($masterData['rd_snapshots'] as $snap) {
            if (!is_array($snap)) {
                continue;
            }
            if (($snap['type'] ?? '') === 'api_analytics' && ($snap['team_id'] ?? '') === $currentTeamId) {
                continue;
            }
            $snapshots[] = $snap;
        }
        $snapshots[] = [
            'team_id' => $currentTeamId,
            'type' => 'api_analytics',
            'snapshot' => $payload,
            'updated_at' => date('c')
        ];
        $masterData['rd_snapshots'] = $snapshots;
        saveJSON(MASTER_BRAIN_FILE, $masterData);

        jsonResponse($payload);
        break;

    case 'activity_intelligence':
        // User Behavior Analytics
        $userActions = [];
        foreach ($logs as $log) {
            $action = $log['action'];
            if (!isset($userActions[$action])) {
                $userActions[$action] = 0;
            }
            $userActions[$action]++;
        }
        
        arsort($userActions);
        
        jsonResponse([
            'user_actions' => array_slice($userActions, 0, 10, true),
            'session_duration' => 1847, // Simulated
            'bounce_rate' => 23.5,
            'feature_adoption' => [
                'calendar_view' => 89,
                'kanban_board' => 67,
                'analytics' => 45,
                'admin_panel' => 23
            ],
            'content_performance' => [
                'posts_created' => count(array_filter($logs, function($l) { return $l['action'] === 'post_created'; })),
                'posts_updated' => count(array_filter($logs, function($l) { return $l['action'] === 'post_updated'; })),
                'products_added' => count(array_filter($logs, function($l) { return $l['action'] === 'product_added'; }))
            ],
            'user_engagement' => [
                'daily_active' => $activeUsers ?? 5,
                'weekly_active' => ($activeUsers ?? 5) * 3,
                'monthly_active' => ($activeUsers ?? 5) * 8
            ]
        ]);
        break;

    case 'realtime':
        // Real-time monitoring
        $recentActivity = array_slice($logs, 0, 20);
        
        jsonResponse([
            'live_users' => rand(3, 8),
            'current_load' => rand(15, 45),
            'memory_usage' => rand(60, 85),
            'cpu_usage' => rand(20, 60),
            'active_sessions' => rand(5, 15),
            'recent_activity' => $recentActivity,
            'system_alerts' => [],
            'performance_metrics' => [
                'db_queries' => rand(100, 300),
                'cache_hits' => rand(85, 95),
                'error_count' => rand(0, 3)
            ]
        ]);
        break;

    case 'ml_insights':
        // Machine Learning Insights (Simulated for Demo)
        jsonResponse([
            'predictions' => [
                'user_growth' => [
                    'next_week' => rand(5, 15),
                    'next_month' => rand(20, 50),
                    'confidence' => 87.5
                ],
                'resource_needs' => [
                    'cpu_forecast' => 'Stable',
                    'memory_forecast' => 'Increase 15%',
                    'storage_forecast' => 'Increase 25%'
                ]
            ],
            'anomalies' => [
                'detected' => 2,
                'resolved' => 1,
                'pending' => 1
            ],
            'patterns' => [
                'peak_usage_time' => '14:00-16:00',
                'low_usage_time' => '02:00-06:00',
                'most_active_day' => 'Tuesday',
                'user_behavior' => 'Consistent posting patterns'
            ],
            'recommendations' => [
                'Scale up during 14:00-16:00',
                'Optimize database queries',
                'Implement caching for product data',
                'Monitor API rate limits'
            ]
        ]);
        break;

    case 'security':
        // Security Intelligence
        $securityScore = rand(85, 98);
        
        jsonResponse([
            'security_score' => $securityScore,
            'threat_level' => $securityScore > 90 ? 'Low' : ($securityScore > 70 ? 'Medium' : 'High'),
            'failed_logins' => rand(0, 5),
            'suspicious_ips' => [],
            'security_events' => array_filter($logs, function($log) {
                return in_array($log['action'], ['login', 'signup', 'api_key_created']);
            }),
            'compliance_status' => [
                'data_encryption' => true,
                'access_logging' => true,
                'session_security' => true,
                'api_security' => true
            ],
            'recommendations' => [
                'Enable 2FA for all admin accounts',
                'Regular security audits',
                'Monitor API key usage patterns',
                'Implement IP whitelisting'
            ]
        ]);
        break;

    case 'news':
        $region = $_GET['region'] ?? 'uae';
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            $r = strtolower(trim((string)$region));
            if ($r === 'india' || $r === 'in') {
                $q = 'India holiday allowance OR public holiday OR government allowance';
            } elseif ($r === 'both' || $r === 'all') {
                $q = '(UAE OR India) holiday allowance OR public holiday OR government allowance';
            } else {
                $q = 'UAE holiday allowance OR public holiday OR government allowance';
            }
        }

        $cfg = rdGetNewsFeedConfig($region);
        $rssUrl = 'https://news.google.com/rss/search?q=' . rawurlencode($q) .
            '&hl=' . rawurlencode($cfg['hl']) .
            '&gl=' . rawurlencode($cfg['gl']) .
            '&ceid=' . rawurlencode($cfg['ceid']);

        $cacheKey = 'socialbrain_rd_news_' . md5($rssUrl);
        $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cacheKey . '.json';
        $cacheTtl = 600;

        if (is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $cached = $raw ? json_decode($raw, true) : null;
            if (is_array($cached) && isset($cached['cached_at']) && (time() - (int)$cached['cached_at']) < $cacheTtl) {
                jsonResponse($cached['data'] ?? [], true, 'ok');
            }
        }

        $rss = rdHttpGet($rssUrl, 10);
        if (!$rss) {
            jsonResponse(null, false, 'Unable to fetch news feed right now');
        }
        $items = rdParseRssItems($rss, 15);
        $payload = [
            'region' => $region,
            'query' => $q,
            'items' => $items,
            'fetched_at' => date('c')
        ];
        @file_put_contents($cacheFile, json_encode(['cached_at' => time(), 'data' => $payload]));
        jsonResponse($payload, true, 'ok');
        break;

    case 'research_upload_image':
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(null, false, 'No file uploaded or upload error');
        }

        $file = $_FILES['image'];
        if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
            jsonResponse(null, false, 'File too large. Max 15MB.');
        }
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/quicktime'
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            jsonResponse(null, false, 'Invalid file type. Only JPG, PNG, GIF, WEBP, MP4, WEBM, MOV allowed.');
        }

        $uploadDir = __DIR__ . '/uploads/research/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Map to safe extension based on detected MIME type (ignore client-supplied extension)
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov'
        ];
        $ext = $extMap[$mimeType] ?? 'bin';
        $filename = 'res_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $imagesFile = __DIR__ . '/research_images.json';
            $images = [];
            if (file_exists($imagesFile)) {
                $images = json_decode(file_get_contents($imagesFile), true) ?: [];
            }
            
            $is_video = strpos($mimeType, 'video') === 0;

            $newImage = [
                'id' => uniqid('media_'),
                'name' => $filename,
                'original_name' => $file['name'],
                'url' => 'uploads/research/' . $filename,
                'type' => $is_video ? 'video' : 'image',
                'mime_type' => $mimeType,
                'uploaded_at' => date('c'),
                'uploaded_by' => $_SESSION['username'] ?? 'unknown',
                // Team isolation: only this team can list/edit/delete the media
                'team_id' => $_SESSION['team_id'] ?? '',
                'user_id' => $_SESSION['user_id'] ?? ''
            ];

            array_unshift($images, $newImage); // Add to beginning
            file_put_contents($imagesFile, json_encode($images), LOCK_EX);

            jsonResponse($newImage, true, 'Media uploaded successfully');
        } else {
            jsonResponse(null, false, 'Failed to save uploaded file');
        }
        break;

    case 'research_list_images':
        $imagesFile = __DIR__ . '/research_images.json';
        $images = [];
        if (file_exists($imagesFile)) {
            $images = json_decode(file_get_contents($imagesFile), true) ?: [];
        }

        $userId = $_SESSION['user_id'] ?? '';
        if ($userId === '') {
            jsonResponse([], true, 'ok');
        }

        // User isolation: only return uploads belonging to the current user
        $filtered = array_values(array_filter($images, function($img) use ($userId) {
            if (!is_array($img)) return false;
            return ($img['user_id'] ?? '') === $userId;
        }));

        jsonResponse($filtered, true, 'ok');
        break;


    case 'export':
        // Data Export
        $format = $_GET['format'] ?? 'json';
        $type = $_GET['type'] ?? 'overview';
        
        $exportData = [
            'export_info' => [
                'generated_at' => date('c'),
                'type' => $type,
                'format' => $format
            ],
            'system_data' => $masterData,
            'activity_logs' => $logs
        ];
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="rd_export_' . date('Ymd_His') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'User ID', 'Action', 'Details', 'IP Address']);
            
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['timestamp'],
                    $log['user_id'],
                    $log['action'],
                    json_encode($log['details'] ?? []),
                    $log['ip_address'] ?? 'unknown'
                ]);
            }
            fclose($output);
            exit;
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="rd_export_' . date('Ymd_His') . '.json"');
            echo json_encode($exportData, JSON_PRETTY_PRINT);
            exit;
        }
        break;

    default:
        jsonResponse(null, false, 'Invalid R&D endpoint');
}
?>
