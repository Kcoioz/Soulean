<?php
/**
 * admin.php
 * Soulean Admin Panel - Integrated Version
 * UI: Responsive Hybrid Theme (CSS External)
 */

require 'config.php';

// ==========================================
// 0. 基础加载
// ==========================================
soulean_check_csrf();

// 加载数据
$data = soulean_load_data();
// 加载语言包
$langDict = require 'dictionary/admin.php';
// 确定当前语言
$currentLang = $data['settings']['language'] ?? 'zh-CN';
// 兼容旧版 'zh' 值
if ($currentLang === 'zh') $currentLang = 'zh-CN';

// 翻译函数 helper
function __($key) {
    global $langDict, $currentLang;
    // 优先取当前语言，没有则回退到中文，再没有则显示Key
    return $langDict[$currentLang][$key] ?? $langDict['zh-CN'][$key] ?? $key;
}

// 登录验证
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 登出处理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // [Fix] 清除当前设备的 remember_me token，不影响其他设备
    if (!empty($_COOKIE['remember_me'])) {
        $cookie = $_COOKIE['remember_me'];
        if (strpos($cookie, 'json_') === 0) {
            $token = substr($cookie, 5);
            $tokens = $data['settings']['remember_tokens'] ?? [];
            $newTokens = [];
            foreach ($tokens as $entry) {
                if (is_array($entry) && count($entry) >= 2 && !hash_equals($entry[0], $token)) {
                    $newTokens[] = $entry;
                }
            }
            $data['settings']['remember_tokens'] = $newTokens;
            soulean_save_data($data);
        }
        if (isset($pdo) && strpos($cookie, ':') !== false) {
            list($selector) = explode(':', $cookie);
            if (!empty($selector)) {
                $pdo->prepare("DELETE FROM admin_sessions WHERE selector = ?")->execute([$selector]);
            }
        }
        setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// ==========================================
// 1. 核心逻辑处理
// ==========================================

// --- 下载备份 ---
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="soulean_backup_' . date('Ymd_His') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

$message = '';
$msgType = '';
$debugLogStr = ''; 
$htaccessFile = '.htaccess';

// --- 表单提交处理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // [A] 邮件测试实验室
    if ($action === 'test_email') {
        $testType = $_POST['test_type'] ?? 'connection';
        $targetEmail = $data['settings']['recovery_email'] ?? '';
        
        if (empty($targetEmail)) {
            $message = "❌ " . ($currentLang == 'en' ? "Email not configured" : "请先保存接收邮箱");
            $msgType = 'error';
        } else {
            $subject = "";
            $body = "";

            if ($testType === 'connection') {
                $subject = "Soulean SMTP Connection Test";
                $body = "<h3>🎉 Connection Success!</h3><p>Your SMTP settings are working correctly.</p><p>Time: " . date('Y-m-d H:i:s') . "</p>";
            } elseif ($testType === 'reminder') {
                $loginUrl = SITE_URL . "/login.php";
                $subject = "🔔 [Test] Soulean Reminder";
                $body = "<div style='padding:20px;'><h2 style='color:#4f46e5;'>👋 Time to Check-in</h2><p>This is a test reminder.</p><div style='margin:30px 0;'><a href='$loginUrl' style='background:#4f46e5;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold;'>Check In Now</a></div></div>";
            } elseif ($testType === 'report') {
                $subject = "Soulean Status Report [Manual]";
                $body = soulean_generate_report_html($data); 
            }

            $result = soulean_send_mail($targetEmail, $subject, $body);

            if ($result['success']) {
                $message = __('msg_email_sent'); $msgType = 'success';
            } else {
                $message = __('msg_email_fail') . $result['message']; $msgType = 'error';
                if (!empty($result['debug_log'])) {
                    $debugLogStr = implode("\n", $result['debug_log']);
                }
            }
        }
    }

    // [B] 补录日志
    if ($action === 'add_log') {
        $logDate = $_POST['log_date'] ?? '';
        $logType = $_POST['log_type'] ?? '';
        $energyLevel = isset($_POST['energy_level']) ? intval($_POST['energy_level']) : null;
        $relapseReason = $_POST['relapse_reason'] ?? '';

        if ($logDate && $logType) {
            if (!isset($data['logs'][$logDate])) $data['logs'][$logDate] = [];
            
            if ($logType === 'energy') {
                $data['analysis']['energy_logs'][$logDate] = $energyLevel;
            } else {
                $pointMap = $data['settings']['points'] ?? [];
                $data['logs'][$logDate][] = $logType;
                $deduction = floatval($pointMap[$logType] ?? 0);
                $data['score'] -= $deduction;
                if ($logType === 'relapse') {
                    if ($logDate > ($data['start_date'] ?? '')) $data['start_date'] = $logDate;
                    if (!empty($relapseReason)) {
                        $data['analysis']['relapse_records'][] = ['date' => $logDate, 'reason' => $relapseReason];
                    }
                }
                if ($logType === 'sex') {
                    if ($logDate > ($data['start_date'] ?? '')) $data['start_date'] = $logDate;
                }
            }
            $data['score'] = max(0, min(100, $data['score']));
            soulean_save_data($data);
            $message = __('msg_log_added'); $msgType = 'success';
        }
    }

    // [C] 调试模式
    if ($action === 'toggle_debug') {
        $enableDebug = isset($_POST['enable_debug']);
        $configFile = 'db_config.php';
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if (strpos($content, "define('DEBUG_MODE'") === false) {
                $newContent = str_replace('?>', "\ndefine('DEBUG_MODE', " . ($enableDebug ? 'true' : 'false') . ");\n?>", $content);
            } else {
                $newContent = preg_replace("/define\('DEBUG_MODE', .*?\);/", "define('DEBUG_MODE', " . ($enableDebug ? 'true' : 'false') . ");", $content);
            }
            if (file_put_contents($configFile, $newContent)) {
                $message = __('msg_debug_updated'); $msgType = 'success';
                echo "<meta http-equiv='refresh' content='1;url=admin.php'>";
            }
        }
    }

    // [D] 切换存储模式
    if ($action === 'switch_storage') {
        if (defined('STORAGE_MODE') && STORAGE_MODE === 'sqlite') {
            $message = "❌ SQLite Mode cannot be switched automatically."; $msgType = 'error';
        } else {
            $targetMode = $_POST['target_mode'];
            $migrate = isset($_POST['migrate_data']);
            
            if ($targetMode !== STORAGE_MODE) {
                $currentData = soulean_load_data();
                
                // 简单的模式切换逻辑
                $configFile = 'db_config.php';
                $content = file_get_contents($configFile);
                $newContent = preg_replace("/define\('STORAGE_MODE', '.*?'\);/", "define('STORAGE_MODE', '$targetMode');", $content);
                
                if ($targetMode === 'json' && $migrate) {
                    file_put_contents('data.json', json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                
                if (file_put_contents($configFile, $newContent)) {
                    $message = __('msg_storage_switched') . " -> " . strtoupper($targetMode); $msgType = 'success';
                    echo "<meta http-equiv='refresh' content='2;url=admin.php'>";
                }
            }
        }
    }

    // [E] 隐私盾
    if ($action === 'toggle_privacy') {
        if (file_exists($htaccessFile)) {
            unlink($htaccessFile); 
            $message = __('msg_privacy_off'); $msgType = 'warning';
        } else {
            $content = "<Files ~ \"\.(json|db|sqlite|sqlite3)$\">\n    Order allow,deny\n    Deny from all\n    Require all denied\n</Files>\n\n<Files ~ \"(config|db_config)\.php$\">\n    Order allow,deny\n    Deny from all\n    Require all denied\n</Files>\n\nOptions -Indexes";
            file_put_contents($htaccessFile, $content); 
            $message = __('msg_privacy_on'); $msgType = 'success';
        }
    }

    // [F] 保存修正状态
    if ($action === 'save_status') {
        if (isset($_POST['score']) && $_POST['score'] !== '') {
            $data['score'] = max(0, min(100, floatval($_POST['score'])));
        }

        if (isset($_POST['relapse_days']) && $_POST['relapse_days'] !== '') {
            $targetRelapseDays = max(0, intval($_POST['relapse_days']));
            $data['start_date'] = date('Y-m-d', strtotime("-{$targetRelapseDays} days"));
        }

        if (isset($_POST['porn_days']) && $_POST['porn_days'] !== '') {
            $targetPornDays = max(0, intval($_POST['porn_days']));
            $targetPornDate = date('Y-m-d', strtotime("-{$targetPornDays} days"));

            if (!isset($data['logs']) || !is_array($data['logs'])) {
                $data['logs'] = [];
            }

            foreach ($data['logs'] as $date => $types) {
                if (!is_array($types)) continue;
                if ($date > $targetPornDate) {
                    $newTypes = array_values(array_diff($types, ['porn']));
                    if (empty($newTypes)) unset($data['logs'][$date]);
                    else $data['logs'][$date] = $newTypes;
                }
            }

            if (!isset($data['logs'][$targetPornDate]) || !is_array($data['logs'][$targetPornDate])) {
                $data['logs'][$targetPornDate] = [];
            }
            if (!in_array('porn', $data['logs'][$targetPornDate], true)) {
                $data['logs'][$targetPornDate][] = 'porn';
            }
        }

        if (soulean_save_data($data)) { $message = __('msg_status_updated'); $msgType = 'success'; }
    }

    // [G] 保存所有设置
    if ($action === 'save_settings') {
        // 语言设置
        if(isset($_POST['language'])) {
            $data['settings']['language'] = $_POST['language'];
            $currentLang = $_POST['language']; // 立即更新当前视图语言
        }

        $data['settings']['site_title'] = $_POST['site_title'] ?? '';
        $data['settings']['site_description'] = $_POST['site_description'] ?? '';
        $data['settings']['public_mode'] = isset($_POST['public_mode']) ? 1 : 0;
        $data['settings']['gender'] = ($_POST['gender'] ?? 'male') === 'female' ? 'female' : 'male';
        $data['settings']['points'] = [
            'urge' => max(0, floatval($_POST['point_urge'] ?? 0.01)),
            'porn' => max(0, floatval($_POST['point_porn'] ?? 1.0)),
            'emission' => max(0, floatval($_POST['point_emission'] ?? 2.0)),
            'sex' => max(0, floatval($_POST['point_sex'] ?? 3.0)),
            'relapse' => max(0, floatval($_POST['point_relapse'] ?? 3.0)),
        ];
        $data['settings']['energy_chart_days'] = intval($_POST['energy_chart_days'] ?? 7);
        $data['settings']['custom_goals'] = $_POST['custom_goals'] ?? '';
        
        $data['settings']['smtp_host'] = $_POST['smtp_host'] ?? '';
        $data['settings']['smtp_port'] = $_POST['smtp_port'] ?? '';
        $data['settings']['smtp_user'] = $_POST['smtp_user'] ?? '';
        if (!empty($_POST['smtp_pass'])) $data['settings']['smtp_pass'] = $_POST['smtp_pass'];
        $data['settings']['smtp_secure'] = $_POST['smtp_secure'] ?? 'ssl';
        $data['settings']['recovery_email'] = $_POST['recovery_email'] ?? '';

        $remindersRaw = $_POST['reminders'] ?? '';
        $remindersArray = array_map('trim', explode(',', $remindersRaw));
        $remindersArray = array_filter($remindersArray, function($t) { return preg_match('/^\d{2}:\d{2}$/', $t); });
        $data['settings']['reminders'] = array_values($remindersArray);

        $passUpdated = false;
        if (!empty($_POST['new_password'])) {
            $oldPassword = $_POST['old_password'] ?? '';
            if (empty($oldPassword)) {
                $message = __('msg_old_pass_required'); $msgType = 'error';
            } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                $message = __('msg_pass_mismatch'); $msgType = 'error';
            } else {
                // [Security] 验证旧密码
                $oldPasswordOk = false;
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT password FROM admin_users ORDER BY id ASC LIMIT 1");
                    $stmt->execute();
                    $storedHash = $stmt->fetchColumn();
                    if ($storedHash && password_verify($oldPassword, $storedHash)) {
                        $oldPasswordOk = true;
                    }
                } else {
                    // JSON 模式：从 data.json 中验证旧密码
                    $storedHash = $data['settings']['admin_pass'] ?? '';
                    if ($storedHash && password_verify($oldPassword, $storedHash)) {
                        $oldPasswordOk = true;
                    }
                }

                if (!$oldPasswordOk) {
                    $message = __('msg_old_pass_wrong'); $msgType = 'error';
                } else {
                    // 旧密码验证通过，更新新密码
                    $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    if ($pdo) {
                        $stmt = $pdo->prepare("UPDATE admin_users SET password = ?");
                        if ($stmt->execute([$new_hash])) $passUpdated = true;
                    } else {
                        $data['settings']['admin_user'] = $_POST['admin_username'] ?? ($data['settings']['admin_user'] ?? 'admin');
                        $data['settings']['admin_pass'] = $new_hash;
                        $passUpdated = true;
                    }
                }
            }
        }

        if ($msgType !== 'error') {
            if (soulean_save_data($data)) {
                $message = __('msg_saved'); 
                $msgType = 'success';
            }
        }
    }
    
    // [H] 清空/重置
    if ($action === 'clear_logs') { $data['logs'] = []; soulean_save_data($data); $message = __('msg_log_cleared'); $msgType = 'warning'; }
    if ($action === 'factory_reset') { $data = soulean_get_default_data(); soulean_save_data($data); $message = __('msg_reset_done'); $msgType = 'warning'; }

    // [J] 踢出指定设备 session
    if ($action === 'kill_session') {
        $targetSession = $_POST['session_id'] ?? '';
        if (!empty($targetSession) && function_exists('soulean_kill_session')) {
            if (soulean_kill_session($targetSession)) {
                $message = __('session_killed'); $msgType = 'success';
            }
        }
    }

    // [K] 踢出所有其他设备
    if ($action === 'kill_all_sessions') {
        if (function_exists('soulean_kill_all_other_sessions')) {
            $killed = soulean_kill_all_other_sessions();
            if ($killed > 0) {
                $message = __('session_all_killed'); $msgType = 'success';
            } else {
                $message = __('session_no_other'); $msgType = 'warning';
            }
        }
    }
    
    // [I] 导入 JSON 数据
    if ($action === 'import_data') {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $message = __('msg_import_error'); $msgType = 'error';
        } else {
            $tmpFile = $_FILES['import_file']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
            
            if ($ext !== 'json') {
                $message = __('msg_import_format'); $msgType = 'error';
            } else {
                $importData = json_decode(file_get_contents($tmpFile), true);
                if (!is_array($importData)) {
                    $message = __('msg_import_invalid'); $msgType = 'error';
                } else {
                    $override = isset($_POST['override']);
                    if ($override) {
                        if (isset($importData['score'])) $data['score'] = max(0, min(100, floatval($importData['score'])));
                        if (isset($importData['start_date'])) $data['start_date'] = $importData['start_date'];
                        if (isset($importData['logs'])) $data['logs'] = $importData['logs'];
                        if (isset($importData['last_update'])) $data['last_update'] = $importData['last_update'];
                        if (isset($importData['analysis'])) $data['analysis'] = $importData['analysis'];
                    } else {
                        if (isset($importData['logs']) && is_array($importData['logs'])) {
                            foreach ($importData['logs'] as $date => $types) {
                                if (!is_array($types)) continue;
                                if (!isset($data['logs'][$date])) $data['logs'][$date] = [];
                                foreach ($types as $t) {
                                    if (!in_array($t, $data['logs'][$date])) $data['logs'][$date][] = $t;
                                }
                            }
                        }
                        if (isset($importData['analysis']['relapse_records']) && is_array($importData['analysis']['relapse_records'])) {
                            if (!isset($data['analysis']['relapse_records'])) $data['analysis']['relapse_records'] = [];
                            $existing = [];
                            foreach ($data['analysis']['relapse_records'] as $r) { $existing[$r['date']][] = $r['reason']; }
                            foreach ($importData['analysis']['relapse_records'] as $r) {
                                if (!isset($existing[$r['date']]) || !in_array($r['reason'], $existing[$r['date']])) {
                                    $data['analysis']['relapse_records'][] = $r;
                                }
                            }
                        }
                        if (isset($importData['analysis']['energy_logs']) && is_array($importData['analysis']['energy_logs'])) {
                            foreach ($importData['analysis']['energy_logs'] as $d => $v) {
                                $data['analysis']['energy_logs'][$d] = intval($v);
                            }
                        }
                    }
                    if (soulean_save_data($data)) {
                        $message = __('msg_import_done'); $msgType = 'success';
                    } else {
                        $message = __('msg_import_save_fail'); $msgType = 'error';
                    }
                }
            }
        }
    }
}

// ==========================================
// 2. 视图数据准备
// ==========================================
$startDate = $data['start_date'] ?? date('Y-m-d');
$currentScore = $data['score'];
$d1 = new DateTime($startDate); $d2 = new DateTime(); $d1->setTime(0,0,0); $d2->setTime(0,0,0);
$relapseDays = ($d1 > $d2) ? -1 * $d1->diff($d2)->days : $d1->diff($d2)->days;
$pornDays = $relapseDays; // 简化逻辑，实际应从logs计算

// 简单的Porn days计算
$pornDates = [];
if (isset($data['logs']) && is_array($data['logs'])) {
    foreach ($data['logs'] as $date => $entries) {
        if (is_array($entries) && in_array('porn', $entries)) $pornDates[] = $date;
    }
}
if (!empty($pornDates)) {
    rsort($pornDates);
    $lastPorn = new DateTime($pornDates[0]); $lastPorn->setTime(0,0,0);
    $pornDays = ($lastPorn > $d2) ? -1 * $lastPorn->diff($d2)->days : $lastPorn->diff($d2)->days;
}

$isProtected = file_exists($htaccessFile);
$isDebug = defined('DEBUG_MODE') && DEBUG_MODE;
$isPublicMode = !empty($data['settings']['public_mode']);
$currentSecure = $data['settings']['smtp_secure'] ?? 'ssl';

// 热力图数据
$heatmapData = [];
$heatmapStart = new DateTime('-52 weeks');
if ($heatmapStart->format('w') != 0) $heatmapStart->modify('last sunday');
$iterDate = clone $heatmapStart;
$todayObj = new DateTime();
$processedLogs = [];
if (isset($data['logs']) && is_array($data['logs'])) {
    foreach ($data['logs'] as $d => $types) {
        if (!is_array($types)) continue;
        if (in_array('relapse', $types)) $status = 3;
        elseif (in_array('sex', $types)) $status = 3;
        elseif (in_array('porn', $types)) $status = 2;
        elseif (in_array('urge', $types)) $status = 1;
        else $status = 0;
        $processedLogs[$d] = $status;
    }
}
while ($iterDate <= $todayObj) {
    $dStr = $iterDate->format('Y-m-d');
    $val = $processedLogs[$dStr] ?? 0;
    $heatmapData[] = ['date' => $dStr, 'val' => $val];
    $iterDate->modify('+1 day');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('admin_title'); ?></title>
    <link rel="icon" type="image/x-icon" href="./favicon.ico" />
    <link rel="stylesheet" href="css/admin_style.css">
    <script src="js/tailwind.js"></script>
    <script>
        // Theme Logic
        function initTheme() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        initTheme(); // Run immediately

        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        // Tab Logic
        function switchTab(id) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            
            // Update active state for nav items
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            document.getElementById('btn-' + id).classList.add('active');
            
            localStorage.setItem('active_tab', id);
        }
        
        window.onload = function() {
            const lastTab = localStorage.getItem('active_tab');
            if (lastTab && document.getElementById(lastTab)) switchTab(lastTab);
        };

        // UI Helpers
        let confirmCallback = null;
        function showConfirm(message, callback) {
            document.getElementById('confirm-text').innerHTML = message.replace(/\\n/g, '<br>');
            document.getElementById('confirm-modal').classList.remove('hidden');
            confirmCallback = callback;
        }
        function closeConfirm() { document.getElementById('confirm-modal').classList.add('hidden'); confirmCallback = null; }
        function doConfirm() { if (confirmCallback) confirmCallback(); closeConfirm(); }
        function handleFormSubmit(event, message) {
            event.preventDefault(); showConfirm(message, () => event.target.submit()); return false;
        }
        function toggleFields(type) {
            document.getElementById('field-energy').style.display = type === 'energy' ? 'block' : 'none';
            document.getElementById('field-reason').style.display = type === 'relapse' ? 'block' : 'none';
        }
        function testEmail(type, label) {
            showConfirm('<?php echo __("confirm_email_test"); ?>', () => {
                const form = document.getElementById('testEmailForm');
                form.test_type.value = type;
                form.submit();
            });
        }
    </script>
</head>
<body class="min-h-screen p-4 md:p-8 flex justify-center items-start">

    <!-- Hidden Form for Email Test -->
    <form id="testEmailForm" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
        <input type="hidden" name="action" value="test_email">
        <input type="hidden" name="test_type" value="connection">
    </form>

    <!-- Modal -->
    <div id="confirm-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm transition-opacity" onclick="closeConfirm()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl animate-modal border border-white/60 dark:border-slate-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2"><?php echo __('confirm_title'); ?></h3>
            <p id="confirm-text" class="text-gray-600 dark:text-gray-400 mb-6 text-sm leading-relaxed"></p>
            <div class="flex gap-3 justify-end">
                <button onclick="closeConfirm()" class="px-4 py-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700 rounded-lg text-sm font-medium transition"><?php echo __('btn_cancel'); ?></button>
                <button onclick="doConfirm()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold shadow-lg transition transform active:scale-95"><?php echo __('btn_confirm'); ?></button>
            </div>
        </div>
    </div>

    <!-- Layout -->
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="sidebar p-5 flex flex-col h-auto lg:sticky lg:top-6 rounded-2xl">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-lg">S</div>
                    <div>
                        <span class="font-bold text-gray-800 dark:text-gray-200 text-base">Soulean</span>
                        <span class="block text-[10px] text-gray-400 uppercase tracking-widest"><?php echo __('version'); ?></span>
                    </div>
                </div>
                <nav class="space-y-1 flex-1">
                    <button id="btn-tab-security" onclick="switchTab('tab-security')" class="nav-item active">
                        <span class="text-base">🛡️</span> <?php echo __('nav_security'); ?>
                    </button>
                    <button id="btn-tab-status" onclick="switchTab('tab-status')" class="nav-item">
                        <span class="text-base">📊</span> <?php echo __('nav_status'); ?>
                    </button>
                    <button id="btn-tab-settings" onclick="switchTab('tab-settings')" class="nav-item">
                        <span class="text-base">⚙️</span> <?php echo __('nav_settings'); ?>
                    </button>
                    <button id="btn-tab-sessions" onclick="switchTab('tab-sessions')" class="nav-item">
                        <span class="text-base">📱</span> <?php echo __('nav_sessions'); ?>
                    </button>
                </nav>
                <div class="pt-5 mt-4 border-t border-gray-100 dark:border-slate-800 space-y-2">
                    <button onclick="toggleTheme()" class="w-full text-xs py-2 px-3 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition font-medium flex items-center justify-center gap-1.5">
                        <span class="dark:hidden">🌞</span><span class="hidden dark:inline">🌙</span>
                        <span class="dark:hidden">Light</span><span class="hidden dark:inline">Dark</span>
                    </button>
                    <div class="flex justify-between items-center text-xs px-1 pt-1">
                        <a href="index.php" class="text-gray-400 hover:text-blue-600 font-medium transition">← <?php echo __('back_home'); ?></a>
                        <a href="?action=logout" class="text-red-400 hover:text-red-600 font-medium transition"><?php echo __('logout'); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="lg:col-span-3 space-y-5 pb-8">
            <div class="dark-card p-5 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200"><?php echo __('admin_title'); ?></h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?php echo __('welcome'); ?> · <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                <div class="text-xs px-3 py-1.5 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 font-medium">Soulean Console</div>
            </div>

            <?php if ($message): ?>
                <div class="p-4 rounded-xl text-sm font-medium animate-fade-in <?php echo $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-100 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800' : ($msgType === 'error' ? 'bg-red-50 text-red-700 border border-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800' : 'bg-orange-50 text-orange-700 border border-orange-100 dark:bg-orange-900/20 dark:text-orange-400 dark:border-orange-800'); ?>">
                    <?php echo $message; ?>
                    <?php if ($debugLogStr): ?>
                        <div class="mt-2 p-3 bg-black/5 dark:bg-black/30 rounded text-[10px] font-mono whitespace-pre-wrap break-all max-h-40 overflow-y-auto border border-black/5 dark:border-white/10"><?php echo htmlspecialchars($debugLogStr); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 1: Security -->
            <div id="tab-security" class="tab-content">
                <div class="dark-card p-6 mb-6">
                    <h2 class="section-title flex items-center gap-2"><span class="bg-blue-100 text-blue-600 p-1 rounded">💾</span> <?php echo __('storage_mode'); ?></h2>
                    <?php if (defined('STORAGE_MODE') && STORAGE_MODE === 'sqlite'): ?>
                        <div class="bg-orange-50 text-orange-900 dark:bg-orange-900/20 dark:text-orange-300 p-4 rounded-lg border border-orange-100 dark:border-orange-800"><p class="font-bold"><?php echo __('mode_sqlite_warn'); ?></p><p class="text-sm opacity-80 mt-1"><?php echo __('mode_sqlite_desc'); ?></p></div>
                    <?php else: ?>
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800 mb-5">
                            <p class="text-sm text-yellow-800 dark:text-yellow-400 font-bold flex items-start gap-2"><span>⚠️</span><span><?php echo __('mode_switch_warn'); ?></span></p>
                        </div>
                        <form method="POST" onsubmit="return handleFormSubmit(event, '<?php echo __('confirm_switch_storage'); ?>')">
                            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="switch_storage">
                            <div class="flex flex-col gap-3">
                                <label class="flex items-center space-x-3 p-3 border dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer"><input type="radio" name="target_mode" value="json" <?php echo STORAGE_MODE === 'json' ? 'checked' : ''; ?> class="text-blue-600"><span><?php echo __('mode_json'); ?></span></label>
                                <label class="flex items-center space-x-3 p-3 border dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer"><input type="radio" name="target_mode" value="mysql" <?php echo STORAGE_MODE === 'mysql' ? 'checked' : ''; ?> class="text-blue-600"><span><?php echo __('mode_mysql'); ?></span></label>
                                <div class="pt-3 flex justify-between items-center">
                                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer"><input type="checkbox" name="migrate_data" value="1" checked class="text-blue-600 rounded"><span><?php echo __('try_migrate'); ?></span></label>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition"><?php echo __('apply_changes'); ?></button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="dark-card p-6 mb-6">
                    <div class="flex justify-between items-start mb-4">
                        <div><h2 class="section-title mb-0"><?php echo __('privacy_shield'); ?></h2><p class="text-sm text-gray-500 mt-1"><?php echo __('privacy_desc'); ?></p></div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle_privacy">
                            <?php if ($isProtected): ?><button type="submit" class="bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 px-3 py-1.5 rounded text-xs font-bold border border-gray-200 dark:border-slate-600"><?php echo __('btn_inactive'); ?></button><?php else: ?><button type="submit" class="bg-green-600 text-white px-3 py-1.5 rounded text-xs font-bold shadow-lg"><?php echo __('btn_active'); ?></button><?php endif; ?>
                        </form>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-400 text-xs p-4 rounded-lg border border-gray-200 dark:border-slate-700">
                        <p class="font-bold mb-2 text-gray-800 dark:text-gray-200"><?php echo __('nginx_warn_title'); ?></p>
                        <p class="mb-2"><?php echo __('nginx_warn_desc'); ?></p>
                        <div class="p-3 bg-gray-800 text-green-400 font-mono rounded overflow-x-auto select-all">location ~ \.(db|sqlite|sqlite3|json)$ { deny all; return 403; }<br><br>location ~ /(config|db_config)\.php$ { deny all; return 403; }</div>
                    </div>
                </div>
                
                <div class="dark-card p-6 flex justify-between items-center mb-6">
                    <div><h2 class="section-title mb-0"><?php echo __('data_backup'); ?></h2><p class="text-sm text-gray-500 mt-1"><?php echo __('data_backup_desc'); ?></p></div>
                    <a href="?action=download_backup" class="text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 px-5 py-2.5 rounded-lg border border-blue-200 dark:border-blue-800 text-sm font-bold transition flex items-center gap-2"><span>📥</span> <?php echo __('btn_download'); ?></a>
                </div>
                
                <div class="dark-card p-6 mb-6">
                    <h2 class="section-title flex items-center gap-2"><span class="bg-green-100 text-green-600 p-1 rounded">📤</span> <?php echo __('data_import'); ?></h2>
                    <p class="text-sm text-gray-500 mb-4"><?php echo __('data_import_desc'); ?></p>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="import_data">
                        <div class="flex items-center gap-3">
                            <label class="flex-1 relative cursor-pointer">
                                <input type="file" name="import_file" accept=".json" required class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                <div class="bg-gray-50 dark:bg-slate-800 border border-dashed border-gray-300 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center hover:border-blue-400 transition">
                                    📁 <span class="font-medium"><?php echo __('choose_file'); ?></span>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg"><?php echo __('data_import_warn'); ?></p>
                        <div class="flex gap-3">
                            <button type="submit" name="override" value="1" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-lg text-sm font-bold shadow transition"><?php echo __('btn_override'); ?></button>
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg text-sm font-bold shadow transition"><?php echo __('btn_merge'); ?></button>
                        </div>
                    </form>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    <div class="dark-card p-4 flex justify-between items-center">
                        <div><span class="font-bold text-gray-700 dark:text-gray-300 text-sm">🐛 <?php echo __('debug_mode'); ?></span><span class="text-xs text-gray-400 ml-2"><?php echo __('debug_desc'); ?></span></div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle_debug">
                            <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="enable_debug" value="1" class="sr-only peer" onchange="this.form.submit()" <?php echo $isDebug ? 'checked' : ''; ?>><div class="w-9 h-5 bg-gray-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-orange-500"></div></label>
                        </form>
                    </div>
                    <div class="dark-card p-4 text-xs text-gray-500 flex flex-col justify-center space-y-1">
                        <div class="flex justify-between border-b border-gray-100 dark:border-slate-700 pb-1"><span>PHP Ver:</span> <span class="font-mono font-bold text-gray-700 dark:text-gray-300"><?php echo phpversion(); ?></span></div>
                        <div class="flex justify-between pt-1"><span>Software:</span> <span class="font-mono font-bold text-gray-700 dark:text-gray-300 truncate max-w-[120px]"><?php echo explode(' ', $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')[0]; ?></span></div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Status -->
            <div id="tab-status" class="tab-content hidden">
                <div class="dark-card p-6 mb-6 overflow-hidden">
                    <h2 class="section-title"><?php echo __('heatmap'); ?></h2>
                    <div class="heatmap-grid w-full">
                        <?php foreach($heatmapData as $day): ?>
                            <div class="hm-cell" data-val="<?php echo $day['val']; ?>" title="<?php echo $day['date']; ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dark-card p-6 mb-6">
                    <h2 class="section-title flex items-center gap-2"><span class="bg-indigo-100 text-indigo-600 p-1 rounded">📝</span> <?php echo __('add_log_title'); ?></h2>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end" onsubmit="return handleFormSubmit(event, '<?php echo __('confirm_add_log'); ?>')">
                        <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add_log">
                        <div class="md:col-span-3"><label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider"><?php echo __('label_date'); ?></label><input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required class="dark-input"></div>
                        <div class="md:col-span-4"><label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider"><?php echo __('label_type'); ?></label>
                            <div class="pretty-select-wrapper">
                                <select name="log_type" required class="dark-input" onchange="toggleFields(this.value)">
                                    <option value="" disabled selected><?php echo __('select_type'); ?></option>
                                    <option value="energy"><?php echo __('type_energy'); ?></option>
                                    <option value="urge"><?php echo __('type_urge'); ?></option>
                                    <option value="porn"><?php echo __('type_porn'); ?></option>
                                    <option value="emission"><?php echo __('type_emission'); ?></option>
                                    <option value="relapse"><?php echo __('type_relapse'); ?></option>
                                    <option value="sex"><?php echo __('type_sex'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div id="field-energy" class="md:col-span-3" style="display:none;">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider"><?php echo __('label_score'); ?></label>
                            <input type="number" name="energy_level" min="1" max="10" placeholder="1-10" class="dark-input">
                        </div>
                        <div id="field-reason" class="md:col-span-3" style="display:none;">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider"><?php echo __('label_reason'); ?></label>
                            <input type="text" name="relapse_reason" placeholder="..." class="dark-input">
                        </div>
                        <div class="md:col-span-2"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg font-bold shadow-md transition"><?php echo __('btn_submit'); ?></button></div>
                    </form>
                </div>

                <div class="dark-card p-6 mb-6">
                    <h2 class="section-title border-b border-slate-200 dark:border-slate-700 pb-2"><?php echo __('data_correction'); ?></h2>
                    <form method="POST" class="space-y-4" onsubmit="return handleFormSubmit(event, '<?php echo __('confirm_save_status'); ?>')">
                        <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="save_status">
                        <div><label class="text-sm font-bold text-gray-500"><?php echo __('current_score'); ?></label><input type="number" step="0.01" name="score" value="<?php echo htmlspecialchars($currentScore); ?>" class="dark-input font-mono text-xl text-blue-600 dark:text-blue-400"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="text-sm font-bold text-gray-500"><?php echo __('days_clean'); ?></label><input type="number" name="relapse_days" value="<?php echo htmlspecialchars($relapseDays); ?>" class="dark-input"></div>
                            <div><label class="text-sm font-bold text-gray-500"><?php echo __('days_porn_free'); ?></label><input type="number" name="porn_days" value="<?php echo htmlspecialchars($pornDays); ?>" class="dark-input"></div>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold mt-2"><?php echo __('btn_save_status'); ?></button>
                    </form>
                </div>

                <div class="dark-card p-6 border-l-4 border-l-red-400 bg-red-50/30 dark:bg-red-900/10">
                    <h3 class="text-sm font-bold text-red-600 dark:text-red-400 mb-3"><?php echo __('danger_zone'); ?></h3>
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" onsubmit="return handleFormSubmit(event, '<?php echo __('confirm_clear_logs'); ?>')" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="clear_logs">
                            <button class="text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 px-4 py-2 rounded-lg border border-red-200 dark:border-red-800 text-sm font-bold transition"><?php echo __('btn_clear_logs'); ?></button>
                        </form>
                        <form method="POST" onsubmit="return handleFormSubmit(event, '<?php echo __('confirm_factory_reset'); ?>')" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="factory_reset">
                            <button class="text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 px-4 py-2 rounded-lg border border-red-300 dark:border-red-700 text-sm font-bold transition"><?php echo __('btn_factory_reset'); ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Settings -->
            <div id="tab-settings" class="tab-content hidden">
                <div class="dark-card p-6">
                    <h2 class="section-title"><?php echo __('settings_title'); ?></h2>
                    <form method="POST" class="space-y-5" onsubmit="return handleFormSubmit(event, '<?php echo __('confirm_save_settings'); ?>')">
                        <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <!-- Language -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-100 dark:border-slate-700">
                            <h3 class="text-xs font-bold text-gray-500 uppercase mb-3 tracking-wider">Language</h3>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="language" value="zh-CN" <?php echo $currentLang==='zh-CN'?'checked':''; ?> class="text-blue-600">
                                    <span class="text-sm">简体中文</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="language" value="zh-TW" <?php echo $currentLang==='zh-TW'?'checked':''; ?> class="text-blue-600">
                                    <span class="text-sm">繁體中文</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="language" value="en" <?php echo $currentLang==='en'?'checked':''; ?> class="text-blue-600">
                                    <span class="text-sm">English</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase block mb-1"><?php echo __('site_name'); ?></label><input type="text" name="site_title" value="<?php echo htmlspecialchars($data['settings']['site_title'] ?? 'Soulean 清心'); ?>" class="dark-input"></div>
                            <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase block mb-1"><?php echo __('site_desc'); ?></label><textarea name="site_description" rows="2" class="dark-input"><?php echo htmlspecialchars($data['settings']['site_description'] ?? ''); ?></textarea></div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider"><?php echo __('chart_days'); ?></label>
                                <input type="number" name="energy_chart_days" value="<?php echo htmlspecialchars($data['settings']['energy_chart_days'] ?? 7); ?>" class="dark-input">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider"><?php echo __('custom_goals'); ?></label>
                                <input type="text" name="custom_goals" value="<?php echo htmlspecialchars($data['settings']['custom_goals'] ?? ''); ?>" placeholder="e.g. 50, 150, 365" class="dark-input">
                                <p class="text-[10px] text-gray-400 mt-1"><?php echo __('custom_goals_desc'); ?></p>
                            </div>

                            <div class="flex items-center gap-4 p-3 rounded-xl border border-green-100 dark:border-green-800 bg-green-50/50 dark:bg-green-900/10">
                                <div class="flex-1"><span class="font-bold text-green-800 dark:text-green-400 text-sm"><?php echo __('public_mode'); ?></span><p class="text-xs text-green-600 dark:text-green-500 mt-0.5"><?php echo __('public_mode_desc'); ?></p></div>
                                <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="public_mode" value="1" class="sr-only peer" <?php echo $isPublicMode ? 'checked' : ''; ?>><div class="w-9 h-5 bg-gray-200 dark:bg-slate-700 border-2 border-transparent rounded-full peer peer-checked:bg-green-500 peer-focus:ring-2 peer-focus:ring-green-300"></div></label>
                            </div>

                            <?php $gender = $data['settings']['gender'] ?? 'male'; ?>
                            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 border border-purple-100 dark:border-purple-800">
                                <h3 class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase mb-3 tracking-wider"><?php echo __('gender_mode'); ?></h3>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="gender" value="male" <?php echo $gender==='male'?'checked':''; ?> class="text-purple-600">
                                        <span class="text-sm"><?php echo __('gender_male'); ?></span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="gender" value="female" <?php echo $gender==='female'?'checked':''; ?> class="text-purple-600">
                                        <span class="text-sm"><?php echo __('gender_female'); ?></span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-purple-500 dark:text-purple-400 mt-2"><?php echo __('gender_desc'); ?></p>
                            </div>

                            <?php $points = $data['settings']['points'] ?? []; ?>
                            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-100 dark:border-amber-800">
                                <h3 class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase mb-3 tracking-wider"><?php echo __('point_settings'); ?></h3>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 block mb-1"><?php echo __('urge_btn'); ?> (<?php echo __('point_default'); ?> 0.01)</label>
                                        <input type="number" step="0.01" name="point_urge" value="<?php echo htmlspecialchars($points['urge'] ?? 0.01); ?>" class="dark-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 block mb-1"><?php echo __('porn_btn'); ?> (<?php echo __('point_default'); ?> 1.0)</label>
                                        <input type="number" step="0.01" name="point_porn" value="<?php echo htmlspecialchars($points['porn'] ?? 1.0); ?>" class="dark-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 block mb-1"><?php echo __('emission_btn'); ?> (<?php echo __('point_default'); ?> 2.0)</label>
                                        <input type="number" step="0.01" name="point_emission" value="<?php echo htmlspecialchars($points['emission'] ?? 2.0); ?>" class="dark-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 block mb-1"><?php echo __('sex_btn'); ?> (<?php echo __('point_default'); ?> 3.0)</label>
                                        <input type="number" step="0.01" name="point_sex" value="<?php echo htmlspecialchars($points['sex'] ?? 3.0); ?>" class="dark-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 block mb-1"><?php echo __('relapse_btn'); ?> (<?php echo __('point_default'); ?> 3.0)</label>
                                        <input type="number" step="0.01" name="point_relapse" value="<?php echo htmlspecialchars($points['relapse'] ?? 3.0); ?>" class="dark-input text-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-2 pt-2 border-t border-slate-200 dark:border-slate-700"><h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"><?php echo __('smtp_config'); ?></h3></div>
                            <div><label class="text-xs font-bold text-gray-400"><?php echo __('host'); ?></label><input type="text" name="smtp_host" value="<?php echo htmlspecialchars($data['settings']['smtp_host'] ?? ''); ?>" class="dark-input"></div>
                            <div class="flex gap-2">
                                <div class="flex-1"><label class="text-xs font-bold text-gray-400"><?php echo __('port'); ?></label><input type="text" name="smtp_port" value="<?php echo htmlspecialchars($data['settings']['smtp_port'] ?? ''); ?>" class="dark-input"></div>
                                <div class="flex-1"><label class="text-xs font-bold text-gray-400"><?php echo __('secure'); ?></label><select name="smtp_secure" class="dark-input h-[42px]"><option value="ssl" <?php echo $currentSecure === 'ssl' ? 'selected' : ''; ?>>SSL</option><option value="tls" <?php echo $currentSecure === 'tls' ? 'selected' : ''; ?>>TLS</option></select></div>
                            </div>
                            <div><label class="text-xs font-bold text-gray-400"><?php echo __('user'); ?></label><input type="text" name="smtp_user" value="<?php echo htmlspecialchars($data['settings']['smtp_user'] ?? ''); ?>" class="dark-input"></div>
                            <div><label class="text-xs font-bold text-gray-400"><?php echo __('pass'); ?></label><input type="password" name="smtp_pass" placeholder="••••••" class="dark-input"></div>
                            <div class="md:col-span-2"><label class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase"><?php echo __('recovery_email'); ?></label><input type="text" name="recovery_email" value="<?php echo htmlspecialchars($data['settings']['recovery_email'] ?? ''); ?>" class="dark-input"></div>

                            <div class="md:col-span-2 bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-xl border border-indigo-100 dark:border-indigo-800 mt-4">
                                <h3 class="font-bold text-indigo-800 dark:text-indigo-300 text-sm mb-4 flex items-center justify-between"><?php echo __('automation_title'); ?></h3>
                                <div class="mb-4"><label class="text-xs text-indigo-600 dark:text-indigo-400 block mb-1"><?php echo __('reminder_times'); ?></label><input type="text" name="reminders" placeholder="09:00, 22:30" value="<?php echo htmlspecialchars(implode(', ', $data['settings']['reminders'] ?? [])); ?>" class="dark-input border-indigo-200 dark:border-indigo-700 focus:border-indigo-400"></div>
                                <div class="bg-white dark:bg-slate-800 p-3 rounded-lg border border-indigo-100 dark:border-slate-700 text-xs">
                                    <p class="font-bold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('cron_guide_title'); ?></p>
                                    <ol class="list-decimal list-inside text-gray-500 dark:text-gray-400 space-y-1 mb-2"><li><?php echo __('cron_step_1'); ?></li><li><?php echo __('cron_step_2'); ?></li></ol>
                                    <div class="flex items-center gap-2">
                                        <?php $cronUrl = SITE_URL . "/cron.php?key=" . (function_exists('soulean_get_cron_secret') ? soulean_get_cron_secret() : ''); ?>
                                        <input type="text" readonly value="<?php echo htmlspecialchars($cronUrl); ?>" class="w-full bg-gray-50 dark:bg-slate-900 text-gray-600 dark:text-gray-400 font-mono text-[10px] border border-dashed border-gray-300 dark:border-slate-600 p-1 rounded" onclick="this.select()">
                                        <span class="text-green-600 dark:text-green-500 font-bold whitespace-nowrap"><?php $lastRun = $data['settings']['last_cron_run'] ?? 0; echo $lastRun ? __('last_run') . date('m-d H:i', $lastRun) : __('never_run'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-2 bg-slate-50 dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 mt-4">
                                <h3 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-3"><?php echo __('mail_lab_title'); ?></h3>
                                <div class="flex flex-wrap gap-3">
                                    <button type="button" onclick="testEmail('connection', 'Conn')" class="text-xs bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-600 px-3 py-2 rounded hover:bg-slate-100 dark:hover:bg-slate-600 transition shadow-sm flex items-center gap-1"><?php echo __('btn_test_conn'); ?></button>
                                    <button type="button" onclick="testEmail('reminder', 'Mock')" class="text-xs bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 border border-indigo-300 dark:border-indigo-800 px-3 py-2 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition shadow-sm flex items-center gap-1"><?php echo __('btn_test_mock'); ?></button>
                                    <button type="button" onclick="testEmail('report', 'Report')" class="text-xs bg-white dark:bg-slate-700 text-teal-600 dark:text-teal-400 border border-teal-300 dark:border-teal-800 px-3 py-2 rounded hover:bg-teal-50 dark:hover:bg-teal-900/30 transition shadow-sm flex items-center gap-1"><?php echo __('btn_test_report'); ?></button>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-2"><?php echo __('test_warn'); ?></p>
                            </div>
                            
                            <div class="md:col-span-2 pt-2 border-t border-slate-200 dark:border-slate-700"><h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"><?php echo __('change_pass_title'); ?></h3></div>
                            <div><input type="password" name="old_password" placeholder="<?php echo __('old_pass'); ?>" class="dark-input"></div>
                            <div><input type="password" name="new_password" placeholder="<?php echo __('new_pass'); ?>" class="dark-input bg-yellow-50 dark:bg-yellow-900/10"></div>
                            <div><input type="password" name="confirm_password" placeholder="<?php echo __('confirm_pass'); ?>" class="dark-input bg-yellow-50 dark:bg-yellow-900/10"></div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-blue-200 transition mt-2 active:scale-[0.98]"><?php echo __('btn_save_all'); ?></button>
                    </form>
                </div>
            </div>

            <!-- TAB 4: Session Management (登录会话管理) -->
            <div id="tab-sessions" class="tab-content hidden">
                <div class="dark-card p-6 mb-6">
                    <h2 class="section-title flex items-center gap-2">
                        <span class="bg-purple-100 text-purple-600 p-1 rounded">📱</span> <?php echo __('session_title'); ?>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                        <?php
                        $activeSessions = function_exists('soulean_get_active_sessions') ? soulean_get_active_sessions() : [];
                        $sessionCount = count($activeSessions);
                        echo $sessionCount . ' ' . __('session_total');
                        ?>
                    </p>

                    <?php if (empty($activeSessions)): ?>
                        <div class="text-center py-10 text-gray-400 dark:text-gray-500">
                            <div class="text-3xl mb-2">📱</div>
                            <p class="text-sm"><?php echo __('session_none'); ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Kick All Other Devices -->
                        <?php
                        $otherCount = 0;
                        foreach ($activeSessions as $s) { if (!($s['is_current'] ?? false)) $otherCount++; }
                        if ($otherCount > 0):
                        ?>
                        <div class="mb-4">
                            <form method="POST" onsubmit="return handleFormSubmit(event, '<?php echo __("session_kill_all_confirm"); ?>')" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                                <input type="hidden" name="action" value="kill_all_sessions">
                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow transition flex items-center gap-2">
                                    <span>⚡</span> <?php echo __('session_kill_all_btn'); ?> (<?php echo $otherCount; ?>)
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- Session Cards (responsive: card layout avoids text overflow) -->
                        <div class="space-y-3">
                            <?php foreach ($activeSessions as $s):
                                $isCurrent = $s['is_current'] ?? false;
                                $deviceIcon = '🖥️';
                                $deviceLabel = __('session_device_desktop');
                                $device = $s['device'] ?? 'Desktop';
                                if ($device === 'Mobile') { $deviceIcon = '📱'; $deviceLabel = __('session_device_mobile'); }
                                if ($device === 'Tablet') { $deviceIcon = '📋'; $deviceLabel = __('session_device_tablet'); }

                                $loginTime = date('m-d H:i', $s['login_time'] ?? 0);
                                $lastAct = isset($s['last_activity']) ? date('m-d H:i', $s['last_activity']) : '—';
                                $browser = $s['browser'] ?? 'Unknown';
                                $os = $s['os'] ?? 'Unknown';
                                $ip = $s['ip'] ?? 'unknown';
                                $activeDot = (time() - ($s['last_activity'] ?? 0) < 300) ? '🟢' : '🟡';
                                $uaShort = mb_substr($s['user_agent'] ?? '', 0, 80);
                            ?>
                            <div class="<?php echo $isCurrent ? 'border-teal-300 dark:border-teal-700 bg-teal-50/30 dark:bg-teal-900/10' : 'border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800'; ?> rounded-xl border p-4 transition hover:shadow-sm">
                                <!-- Row 1: Device + Status -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-xl flex-shrink-0"><?php echo $deviceIcon; ?></span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-bold text-gray-800 dark:text-gray-200 text-sm truncate"><?php echo $deviceLabel; ?></span>
                                                <?php if ($isCurrent): ?>
                                                    <span class="px-1.5 py-0.5 bg-teal-100 dark:bg-teal-800 text-teal-700 dark:text-teal-300 rounded text-[10px] font-bold flex-shrink-0"><?php echo __('session_this_device'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5 truncate"><?php echo htmlspecialchars($os); ?> · <?php echo htmlspecialchars($browser); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 ml-2">
                                        <?php if (!$isCurrent): ?>
                                            <form method="POST" onsubmit="return handleFormSubmit(event, '<?php echo __("session_kill_confirm"); ?>')" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="kill_session">
                                                <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($s['session_id'] ?? ''); ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 px-2.5 py-1 rounded-lg text-[11px] font-bold transition border border-red-200 dark:border-red-800">
                                                    <?php echo __('session_kill_btn'); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-[11px] text-teal-600 dark:text-teal-400 font-bold"><?php echo $activeDot; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Row 2: IP + Time -->
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-gray-400 dark:text-gray-500 flex-shrink-0 text-[10px] uppercase tracking-wider">IP</span>
                                        <span class="text-gray-600 dark:text-gray-400 font-mono text-[11px] truncate" title="<?php echo htmlspecialchars($ip); ?>"><?php echo htmlspecialchars($ip); ?></span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-gray-400 dark:text-gray-500 flex-shrink-0 text-[10px] uppercase tracking-wider">⏱</span>
                                        <span class="text-gray-500 dark:text-gray-500 text-[11px]"><?php echo $loginTime; ?></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-gray-400 dark:text-gray-500 flex-shrink-0 text-[10px] uppercase tracking-wider">🔄</span>
                                        <span class="text-gray-500 dark:text-gray-500 text-[11px]"><?php echo $activeDot; ?> <?php echo $lastAct; ?></span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-gray-400 dark:text-gray-500 flex-shrink-0 text-[10px] uppercase tracking-wider">🔑</span>
                                        <span class="text-gray-500 dark:text-gray-500 text-[11px]">
                                            <?php echo !empty($s['remember_token']) ? '✓' : '—'; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Row 3: UA (collapsible) -->
                                <?php if (!empty($uaShort)): ?>
                                <details class="mt-2">
                                    <summary class="text-[10px] text-gray-400 dark:text-gray-500 cursor-pointer hover:text-gray-600 dark:hover:text-gray-300">UA</summary>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 break-all font-mono"><?php echo htmlspecialchars($s['user_agent'] ?? ''); ?></p>
                                </details>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center text-xs text-gray-300 dark:text-slate-600 py-4">&copy; Soulean Admin</div>
        </div>
    </div>
</body>
</html>
