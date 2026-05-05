<?php
/**
 * config.php - 系统核心配置 & 数据抽象层
 */

// 0. 内存与调试
@ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Shanghai');

// 1. 引入数据库配置
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}
if (!defined('STORAGE_MODE')) define('STORAGE_MODE', 'json');

// 2. 调试与安全
if (defined('DEBUG_MODE') && DEBUG_MODE === true) { 
    ini_set('display_errors', 1); 
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL); 
} else { 
    ini_set('display_errors', 0); 
    ini_set('display_startup_errors', 0);
    error_reporting(0); 
}

// 3. Session 安全 (Lax模式)
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) session_start();

// [Security] 会话超时检查 (24小时无活动自动登出)
// 仅在 last_activity 已初始化时才检查，避免刚登录的 session 被错误销毁
$sessionTimeout = 86400; // 24小时
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // 首次请求：初始化 last_activity (last_activity 未设置时跳过超时检查)
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    } else {
        $lastActivity = $_SESSION['last_activity'];
        if (time() - $lastActivity > $sessionTimeout) {
            // 超时：清除 session
            session_unset();
            session_destroy();
            if (session_status() === PHP_SESSION_NONE) session_start();
        } else {
            $_SESSION['last_activity'] = time(); // 刷新活动时间
        }
    }
    // 刷新 session 追踪列表中的活动时间（10% 概率避免每次请求都写文件）
    if (mt_rand(1, 10) <= 2) {
        if (function_exists('soulean_refresh_session_activity')) {
            soulean_refresh_session_activity();
        }
    }
}

// 4. 全局安全头
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header_remove("X-Powered-By");

if (!defined('STORAGE_MODE')) define('STORAGE_MODE', 'json');

// --- 5. 核心安全：URL 自动识别与 CSRF ---

if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', $host);
    $path = dirname($_SERVER['PHP_SELF']);
    $path = rtrim(str_replace('\\', '/', $path), '/');
    define('SITE_URL', $protocol . $host . $path);
}

function soulean_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function soulean_check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
             http_response_code(403);
             die('<div style="color:red;font-weight:bold;padding:20px;text-align:center;">Security Error: CSRF token validation failed.<br>安全校验失败：请求可能不合法。<br><a href="javascript:history.back()">返回重试</a></div>');
        }
    }
}

// --- 权限验证 ---
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) return;
        if (isset($_GET['action'])) { 
            header('Content-Type: application/json'); 
            http_response_code(401); 
            echo json_encode(['error' => 'Unauthorized']); 
            exit; 
        }
        header('Location: login.php'); exit;
    }
}

// --- 数据库连接 ---
$pdo = null;
if ((STORAGE_MODE === 'mysql' || STORAGE_MODE === 'json') && defined('DB_HOST') && DB_HOST !== '') {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 60); 
    } catch (PDOException $e) { 
        if (basename($_SERVER['PHP_SELF']) !== 'install.php' && STORAGE_MODE !== 'json') {
            die((defined('DEBUG_MODE') && DEBUG_MODE) ? $e->getMessage() : 'Database Connection Failed');
        }
    }
} elseif (STORAGE_MODE === 'sqlite') {
    try {
        $dbFile = __DIR__ . '/soulean.db';
        $pdo = new PDO("sqlite:" . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } catch (PDOException $e) { die("SQLite Error: " . $e->getMessage()); }
}

// --- Cron 密钥管理 ---
function soulean_get_cron_secret() {
    $data = soulean_load_data();
    if (empty($data['settings']['cron_secret'])) {
        $data['settings']['cron_secret'] = bin2hex(random_bytes(16));
        soulean_save_data($data);
    }
    return $data['settings']['cron_secret'];
}

function soulean_update_cron_time() {
    $data = soulean_load_data();
    $data['settings']['last_cron_run'] = time();
    soulean_save_data($data);
}

// --- 核心功能：生成报告 HTML ---
function soulean_generate_report_html($data) {
    $score = $data['score'] ?? 80;
    $date = date('Y-m-d');
    $streak = 0;
    if (!empty($data['start_date'])) {
        $streak = (time() - strtotime($data['start_date'])) / 86400;
        $streak = max(0, floor($streak));
    }
    
    // 计算本周数据
    $weekStart = date('Y-m-d', strtotime('-7 days'));
    $weekLogs = ['porn' => 0, 'relapse' => 0, 'urge' => 0];
    if (!empty($data['logs'])) {
        foreach ($data['logs'] as $d => $types) {
            if ($d >= $weekStart) {
                if (is_array($types)) {
                    $uniqueTypes = array_unique($types);
                    if (in_array('porn', $uniqueTypes)) $weekLogs['porn']++;
                    if (in_array('relapse', $uniqueTypes)) $weekLogs['relapse']++;
                    if (in_array('urge', $uniqueTypes)) $weekLogs['urge']++;
                }
            }
        }
    }

    $energyHtml = "";
    for ($i=6; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $val = $data['analysis']['energy_logs'][$d] ?? '-';
        $energyHtml .= "<li style='margin-bottom:5px;color:#555;'>$d: <b style='color:#0d9488;'>$val</b></li>";
    }

    return "
    <div style='background:#f0fdfa;padding:30px;font-family:sans-serif;'>
        <div style='background:white;padding:40px;border-radius:12px;border:1px solid #ccfbf1;box-shadow:0 10px 25px rgba(0,0,0,0.05);max-width:600px;margin:0 auto;'>
            <h1 style='color:#115e59;border-bottom:2px solid #f0fdfa;padding-bottom:15px;margin-top:0;'>📊 Soulean 进度报告</h1>
            <p style='color:#666;'>生成日期：$date</p>
            
            <div style='display:flex;justify-content:space-around;margin:30px 0;background:#fcfcfc;padding:20px;border-radius:10px;'>
                <div style='text-align:center;'>
                    <div style='font-size:36px;font-weight:bold;color:#0d9488;'>$score</div>
                    <div style='color:#999;font-size:12px;text-transform:uppercase;'>Current Score</div>
                </div>
                <div style='border-left:1px solid #eee;'></div>
                <div style='text-align:center;'>
                    <div style='font-size:36px;font-weight:bold;color:#f59e0b;'>$streak</div>
                    <div style='color:#999;font-size:12px;text-transform:uppercase;'>Clean Days</div>
                </div>
            </div>

            <h3 style='font-size:16px;margin-bottom:10px;color:#333;'>⚡ 近7日精力趋势</h3>
            <ul style='background:#f9fafb;padding:15px 15px 15px 35px;border-radius:8px;font-size:14px;list-style-type:circle;'>$energyHtml</ul>
            
            <h3 style='font-size:16px;margin-bottom:10px;margin-top:20px;color:#333;'>📅 近7日统计</h3>
            <ul style='background:#fff1f2;padding:15px 30px;border-radius:8px;font-size:14px;color:#881337;'>
                <li>🔥 破戒次数: <b>{$weekLogs['relapse']}</b></li>
                <li>👀 看片次数: <b>{$weekLogs['porn']}</b></li>
                <li>🌊 欲望记录: <b>{$weekLogs['urge']}</b></li>
            </ul>

            <p style='font-size:12px;color:#999;margin-top:30px;text-align:center;border-top:1px solid #eee;padding-top:20px;'>
                来自 Soulean 自动化助手 • Keep Growing<br>
                自律即自由！
            </p>
        </div>
    </div>";
}

// =====================================================
// --- 数据抽象层 ---
// =====================================================

function soulean_load_data() {
    if (STORAGE_MODE === 'json') {
        $file = __DIR__ . '/data.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                if (!isset($data['logs'])) $data['logs'] = [];
                if (!isset($data['settings'])) $data['settings'] = [];
                if (!isset($data['last_update'])) $data['last_update'] = date('Y-m-d');
                if (!isset($data['analysis'])) $data['analysis'] = ['relapse_records' => [], 'energy_logs' => [], 'daily_notes' => []];
            if (!isset($data['analysis']['daily_notes'])) $data['analysis']['daily_notes'] = [];
            return $data;
            }
        }
        return soulean_get_default_data();
    } else {
        global $pdo;
        // [Fix] DB 不可用时降级到 JSON 模式，避免 System Error
        if (!$pdo) {
            error_log("Soulean: DB unavailable, falling back to JSON mode.");
            $file = __DIR__ . '/data.json';
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if (is_array($data)) {
                    if (!isset($data['logs'])) $data['logs'] = [];
                    if (!isset($data['settings'])) $data['settings'] = [];
                    if (!isset($data['last_update'])) $data['last_update'] = date('Y-m-d');
                    if (!isset($data['analysis'])) $data['analysis'] = ['relapse_records' => [], 'energy_logs' => [], 'daily_notes' => []];
                    if (!isset($data['analysis']['daily_notes'])) $data['analysis']['daily_notes'] = [];
                    return $data;
                }
            }
            return soulean_get_default_data();
        }
        
        try {
            $stmt = $pdo->query("SELECT * FROM users LIMIT 1");
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            // 错误自愈
            $errMsg = $e->getMessage();
            if (strpos($errMsg, '1146') !== false || strpos($errMsg, 'no such table') !== false) {
                return soulean_auto_repair_db($pdo);
            }
            if (defined('DEBUG_MODE') && DEBUG_MODE) die("DB Read Error: " . $e->getMessage());
            die("System Error: Unable to read user data.");
        }

        if (!$user) return soulean_auto_repair_db($pdo, true);

        // 加载日志
        $logs = [];
        try {
            if (STORAGE_MODE === 'mysql') $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $stmt = $pdo->query("SELECT log_date, log_type FROM daily_logs");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $logs[$r['log_date']][] = $r['log_type']; }
            if (STORAGE_MODE === 'mysql') $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (Exception $e) {}

        // 加载分析数据
        $analysis = ['relapse_records' => [], 'energy_logs' => [], 'daily_notes' => []];
        try {
            $stmt = $pdo->query("SELECT log_date, reason FROM relapse_records");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $analysis['relapse_records'][] = ['date' => $r['log_date'], 'reason' => $r['reason']];
            
            $stmt = $pdo->query("SELECT log_date, energy_level FROM energy_logs");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $analysis['energy_logs'][$r['log_date']] = (int)$r['energy_level'];

            $stmt = $pdo->query("SELECT log_date, note FROM daily_notes");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $analysis['daily_notes'][$r['log_date']] = $r['note'];
        } catch (Exception $e) { 
            soulean_auto_repair_db($pdo, false); 
        }

        $settings = isset($user['settings']) ? json_decode($user['settings'], true) : [];
        return [
            'start_date' => $user['start_date'], 
            'score' => floatval($user['score']), 
            'last_update' => $user['last_update_date'], 
            'logs' => $logs, 
            'settings' => $settings, 
            'analysis' => $analysis
        ];
    }
}

function soulean_auto_repair_db($pdo, $onlyData = false) {
    try {
        if (!$onlyData) {
            $eng = (STORAGE_MODE === 'mysql') ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
            $inc = (STORAGE_MODE === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $dt = (STORAGE_MODE === 'mysql') ? 'DATE' : 'TEXT';
            $ts = (STORAGE_MODE === 'mysql') ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';
            
            // 创建核心表
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (id $inc, start_date $dt, score REAL DEFAULT 80, last_update_date $dt, settings TEXT, created_at $ts) $eng");
            $pdo->exec("CREATE TABLE IF NOT EXISTS daily_logs (id $inc, log_date $dt, log_type TEXT, created_at $ts) $eng");
            // 创建分析表
            $pdo->exec("CREATE TABLE IF NOT EXISTS relapse_records (id $inc, log_date $dt, reason TEXT, created_at $ts) $eng");
            $pdo->exec("CREATE TABLE IF NOT EXISTS energy_logs (id $inc, log_date $dt, energy_level INTEGER, created_at $ts) $eng");
            $pdo->exec("CREATE TABLE IF NOT EXISTS daily_notes (id $inc, log_date $dt, note TEXT, created_at $ts) $eng");
            
            // 创建索引 (优化查询效率)
            if (STORAGE_MODE === 'mysql') {
                // daily_logs: 复合索引加速按日期+类型查询
                try { @$pdo->exec("CREATE INDEX idx_log_date ON daily_logs(log_date)"); } catch(Exception $e){}
                try { @$pdo->exec("CREATE INDEX idx_log_date_type ON daily_logs(log_date, log_type)"); } catch(Exception $e){}
                // energy_logs: 唯一索引防止重复
                try { @$pdo->exec("CREATE UNIQUE INDEX idx_energy_date ON energy_logs(log_date)"); } catch(Exception $e){}
                // daily_notes: 唯一索引
                try { @$pdo->exec("CREATE UNIQUE INDEX idx_note_date ON daily_notes(log_date)"); } catch(Exception $e){}
                // relapse_records: 按日期查询索引
                try { @$pdo->exec("CREATE INDEX idx_relapse_date ON relapse_records(log_date)"); } catch(Exception $e){}
                // admin_sessions: 按 selector 和 expires 查询索引
                try { @$pdo->exec("CREATE INDEX idx_session_sel ON admin_sessions(selector)"); } catch(Exception $e){}
                try { @$pdo->exec("CREATE INDEX idx_session_exp ON admin_sessions(expires)"); } catch(Exception $e){}
            } else {
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_log_date ON daily_logs(log_date)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_log_date_type ON daily_logs(log_date, log_type)");
                $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_energy_date ON energy_logs(log_date)");
                $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_note_date ON daily_notes(log_date)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_relapse_date ON relapse_records(log_date)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_session_sel ON admin_sessions(selector)");
            }
        }
        
        $jsonFile = __DIR__ . '/data.json';
        $dataToRestore = soulean_get_default_data();
        if (file_exists($jsonFile)) {
            $jsonData = json_decode(file_get_contents($jsonFile), true);
            if (is_array($jsonData)) $dataToRestore = $jsonData;
        }

        soulean_save_data($dataToRestore);
        return $dataToRestore;
    } catch (Exception $e) { die("DB Repair Failed: " . $e->getMessage()); }
}

function soulean_save_data($data) {
    if (STORAGE_MODE === 'json') {
        return file_put_contents(__DIR__.'/data.json', json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    } else {
        global $pdo;
        // [Fix] DB 不可用时降级保存到 JSON 文件
        if (!$pdo) {
            error_log("Soulean: DB unavailable for save, falling back to JSON.");
            return file_put_contents(__DIR__.'/data.json', json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
        }
        try {
            $pdo->beginTransaction();
            
            // 1. 保存 Users 核心数据
            $cnt = $pdo->query("SELECT count(*) FROM users")->fetchColumn();
            $set = json_encode($data['settings']??[], JSON_UNESCAPED_UNICODE);
            $sql = $cnt == 0 ? "INSERT INTO users (start_date, score, last_update_date, settings) VALUES (?,?,?,?)" : "UPDATE users SET start_date=?, score=?, last_update_date=?, settings=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['start_date'], $data['score'], $data['last_update'], $set]);
            
            // 2. 保存 Logs (批量插入优化，全量覆盖模式)
            // 日志数据比较复杂且可能有重复类型，全量覆盖是较为安全简单的做法
            $pdo->exec("DELETE FROM daily_logs"); 
            if (!empty($data['logs'])) {
                $batchSize = 200;
                $params = [];
                $placeholders = [];
                $count = 0;
                
                foreach ($data['logs'] as $date => $types) {
                    if (!is_array($types)) continue;
                    $uniqueTypes = array_unique($types);
                    foreach ($uniqueTypes as $type) {
                        $placeholders[] = "(?, ?)";
                        $params[] = $date;
                        $params[] = $type;
                        $count++;
                        if ($count >= $batchSize) {
                            $sql = "INSERT INTO daily_logs (log_date, log_type) VALUES " . implode(',', $placeholders);
                            $pdo->prepare($sql)->execute($params);
                            $params = []; $placeholders = []; $count = 0;
                        }
                    }
                }
                if ($count > 0) {
                    $sql = "INSERT INTO daily_logs (log_date, log_type) VALUES " . implode(',', $placeholders);
                    $pdo->prepare($sql)->execute($params);
                }
            }

            // 3. 保存 Relapse Records (全量覆盖模式)
            // 破戒记录通常较少，全量覆盖风险较低
            $pdo->exec("DELETE FROM relapse_records");
            if (!empty($data['analysis']['relapse_records'])) {
                $stmt = $pdo->prepare("INSERT INTO relapse_records (log_date, reason) VALUES (?, ?)");
                foreach ($data['analysis']['relapse_records'] as $r) $stmt->execute([$r['date'], $r['reason']]);
            }

            // 4. 保存 Energy Logs (UPSERT 模式)
            // 这里修改为先查询，存在则更新，不存在则插入。避免删除 ID 导致主键剧烈变化。
            if (!empty($data['analysis']['energy_logs'])) {
                // 预处理查询、更新和插入语句
                $stmtCheck = $pdo->prepare("SELECT id FROM energy_logs WHERE log_date = ? LIMIT 1");
                $stmtUpdate = $pdo->prepare("UPDATE energy_logs SET energy_level = ? WHERE id = ?");
                $stmtInsert = $pdo->prepare("INSERT INTO energy_logs (log_date, energy_level) VALUES (?, ?)");

                foreach ($data['analysis']['energy_logs'] as $date => $level) {
                    $stmtCheck->execute([$date]);
                    $existingId = $stmtCheck->fetchColumn();

                    if ($existingId) {
                        // 存在 -> 更新
                        $stmtUpdate->execute([$level, $existingId]);
                    } else {
                        // 不存在 -> 插入
                        $stmtInsert->execute([$date, $level]);
                    }
                }
            }

            // 5. 保存 Daily Notes (覆盖模式，确保删除同步)
            $pdo->exec("DELETE FROM daily_notes");
            if (!empty($data['analysis']['daily_notes'])) {
                $stmtInsert = $pdo->prepare("INSERT INTO daily_notes (log_date, note) VALUES (?, ?)");

                foreach ($data['analysis']['daily_notes'] as $date => $note) {
                    if (empty($note)) continue;
                    $stmtInsert->execute([$date, $note]);
                }
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            error_log("Soulean Save Error: " . $e->getMessage()); 
            return false; 
        }
    }
}

function soulean_get_default_data() {
    return ['start_date' => date('Y-m-d'), 'score' => 80.0, 'last_update' => date('Y-m-d'), 'logs' => [], 'settings' => [], 'analysis' => ['relapse_records' => [], 'energy_logs' => [], 'daily_notes' => []]];
}

function soulean_delete_note($date) {
    $data = soulean_load_data();
    if (isset($data['analysis']['daily_notes'][$date])) {
        unset($data['analysis']['daily_notes'][$date]);
        return soulean_save_data($data);
    }
    return false;
}

// 引入邮件模块
require_once __DIR__ . '/mail.php';

// --- 工具函数：清理过期 JSON 模式 remember token ---
function soulean_cleanup_expired_tokens() {
    $data = soulean_load_data();
    $tokens = $data['settings']['remember_tokens'] ?? [];
    if (empty($tokens)) return;
    $currentTime = time();
    $newTokens = [];
    foreach ($tokens as $entry) {
        if (is_array($entry) && count($entry) >= 2) {
            // 30天有效期内的保留
            if ($currentTime - $entry[1] < 86400 * 30) {
                $newTokens[] = $entry;
            }
        }
    }
    // 当有 token 被过期移除时才保存
    if (count($newTokens) !== count($tokens)) {
        $data['settings']['remember_tokens'] = $newTokens;
        soulean_save_data($data);
    }
}

// --- 工具函数：清理所有 session 中的过期 / 无效 remember token ---
function soulean_cleanup_all_tokens() {
    // JSON 模式
    soulean_cleanup_expired_tokens();

    // DB 模式：清理过期 admin_sessions
    global $pdo;
    if (isset($pdo)) {
        try {
            $pdo->exec("DELETE FROM admin_sessions WHERE expires < NOW()");
        } catch (Exception $e) {
            // 静默失败
        }
    }
}

// =====================================================
// --- Session 管理：多设备登录状态追踪与踢下线 ---
// =====================================================

// 解析 User-Agent 提取浏览器和操作系统
function soulean_parse_user_agent($ua) {
    $browser = 'Unknown';
    $os = 'Unknown';
    $device = 'Desktop';

    // OS
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false && stripos($ua, 'Android') === false) $os = 'Linux';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';

    // Browser
    if (stripos($ua, 'Edg/') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Safari/') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Safari/') !== false) $browser = 'Safari';
    elseif (stripos($ua, 'MSIE ') !== false || stripos($ua, 'Trident/') !== false) $browser = 'IE';
    elseif (stripos($ua, 'CriOS/') !== false) $browser = 'Chrome iOS';
    elseif (stripos($ua, 'FxiOS/') !== false) $browser = 'Firefox iOS';

    // Device
    if (stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false) $device = 'Tablet';
    elseif (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false) $device = 'Mobile';

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

// 记录当前会话（登录时调用）
function soulean_track_session() {
    $data = soulean_load_data();
    $sessions = $data['settings']['active_sessions'] ?? [];

    $currentSid = session_id();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // 提取真实 IP
    $ip = explode(',', $ip)[0];
    $ip = trim($ip);
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $parsed = soulean_parse_user_agent($ua);

    // 移除同一 session_id 的旧记录
    $filtered = [];
    foreach ($sessions as $s) {
        if (($s['session_id'] ?? '') !== $currentSid) {
            $filtered[] = $s;
        }
    }

    // 添加当前设备记录
    $filtered[] = [
        'session_id' => $currentSid,
        'ip' => $ip,
        'user_agent' => mb_substr($ua, 0, 300),
        'browser' => $parsed['browser'],
        'os' => $parsed['os'],
        'device' => $parsed['device'],
        'login_time' => time(),
        'last_activity' => time()
    ];

    // 最多保留 30 条，移除最旧的
    if (count($filtered) > 30) {
        usort($filtered, function($a, $b) { return $b['login_time'] - $a['login_time']; });
        $filtered = array_slice($filtered, 0, 30);
    }

    $data['settings']['active_sessions'] = array_values($filtered);
    soulean_save_data($data);
}

// 刷新当前会话的活动时间（每次页面加载时调用）
function soulean_refresh_session_activity() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $data = soulean_load_data();
        $sessions = $data['settings']['active_sessions'] ?? [];
        $currentSid = session_id();
        $changed = false;
        foreach ($sessions as $k => $s) {
            if (($s['session_id'] ?? '') === $currentSid) {
                $sessions[$k]['last_activity'] = time();
                $sessions[$k]['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ($s['ip'] ?? 'unknown');
                $ip = explode(',', $sessions[$k]['ip'])[0];
                $sessions[$k]['ip'] = trim($ip);
                $changed = true;
                break;
            }
        }
        if ($changed) {
            $data['settings']['active_sessions'] = $sessions;
            soulean_save_data($data);
        }
    }
}

// 获取所有活跃会话
function soulean_get_active_sessions() {
    $data = soulean_load_data();
    $sessions = $data['settings']['active_sessions'] ?? [];
    $currentSid = session_id();

    // 清理超过 24 小时无活动的
    $now = time();
    $filtered = [];
    foreach ($sessions as $s) {
        if ($now - ($s['last_activity'] ?? 0) < 86400) {
            $s['is_current'] = ($s['session_id'] ?? '') === $currentSid;
            $filtered[] = $s;
        }
    }

    if (count($filtered) !== count($sessions)) {
        $data['settings']['active_sessions'] = $filtered;
        soulean_save_data($data);
    }

    // 按最后活动时间倒序
    usort($filtered, function($a, $b) { return ($b['last_activity'] ?? 0) - ($a['last_activity'] ?? 0); });

    return $filtered;
}

// 踢出指定会话（删除 session + remember token + session 记录）
function soulean_kill_session($sessionId) {
    if ($sessionId === session_id()) return false; // 不能踢自己

    $data = soulean_load_data();
    $sessions = $data['settings']['active_sessions'] ?? [];
    $removed = false;
    $filtered = [];
    foreach ($sessions as $s) {
        if (($s['session_id'] ?? '') === $sessionId) {
            $removed = true;
            continue;
        }
        $filtered[] = $s;
    }

    if ($removed) {
        $data['settings']['active_sessions'] = $filtered;
        soulean_save_data($data);
    }

    return $removed;
}

// 踢出所有其他设备（保留当前）
function soulean_kill_all_other_sessions() {
    $currentSid = session_id();
    $data = soulean_load_data();
    $sessions = $data['settings']['active_sessions'] ?? [];
    $filtered = [];
    $killed = 0;

    foreach ($sessions as $s) {
        if (($s['session_id'] ?? '') === $currentSid) {
            $filtered[] = $s;
        } else {
            $killed++;
        }
    }

    if ($killed > 0) {
        $data['settings']['active_sessions'] = $filtered;
        soulean_save_data($data);

        // 也清除其他设备的 remember tokens
        if (isset($data['settings']['remember_tokens']) && is_array($data['settings']['remember_tokens'])) {
            // 只保留最近的（当前设备的 token 无法精确匹配，保留全部）
            // 改为：清除所有 remember tokens，当前设备会在下次记住我时重新生成
            $data['settings']['remember_tokens'] = [];
            soulean_save_data($data);
        }

        // DB 模式：清除所有 admin_sessions
        global $pdo;
        if (isset($pdo)) {
            try {
                $pdo->exec("DELETE FROM admin_sessions");
            } catch (Exception $e) {}
        }
    }

    return $killed;
}
?>