<?php
/**
 * login.php - Soulean Login with Secure "Remember Me"
 */

require_once 'config.php';

// 引入邮件组件
if (file_exists('mail.php')) {
    require_once 'mail.php';
} else {
    function soulean_send_mail() { return ['success'=>false, 'message'=>'Mail component missing']; }
}

$error = ''; $success = '';
$view = $_GET['action'] ?? 'login'; // login, forgot, reset
$token = $_GET['token'] ?? '';

// --- 多语言处理 ---
$frontendDict = require 'dictionary/frontend.php';
$data = soulean_load_data();

// [Fix] 语言选择处理：允许在登录页切换语言，存储在 session 中 (使用 GET 避免 CSRF 冲突)
if (isset($_GET['set_lang'])) {
    $allowedLangs = ['zh-CN', 'zh-TW', 'en'];
    $newLang = trim(strip_tags($_GET['set_lang']));
    if (in_array($newLang, $allowedLangs)) {
        $_SESSION['lang'] = $newLang;
        // 如果是已登录用户，同时保存到设置
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $data['settings']['language'] = $newLang;
            soulean_save_data($data);
        }
    }
    // 重定向去除 GET 参数避免刷新重复提交
    header('Location: login.php' . ($view !== 'login' ? '?action=' . urlencode($view) : ''));
    exit;
}

$currentLang = $_SESSION['lang'] ?? ($data['settings']['language'] ?? 'zh-CN');
// 兼容旧版 'zh' 值
if ($currentLang === 'zh') $currentLang = 'zh-CN';

// [Fix] 定期清理过期 token，避免 data.json 无限膨胀
if (mt_rand(1, 100) <= 10) { // 10% 概率触发清理
    if (function_exists('soulean_cleanup_all_tokens')) {
        soulean_cleanup_all_tokens();
    }
}
$lang = $frontendDict[$currentLang] ?? $frontendDict['zh-CN'];

function __($key) {
    global $lang;
    return $lang[$key] ?? $key;
}

// ==========================================
// [Security] 自动登录检查 (Persistent Login)
// ==========================================
// 支持两种方式：DB模式(admin_sessions表) 和 JSON模式(data.json 存储)
if (empty($_SESSION['logged_in']) && !empty($_COOKIE['remember_me'])) {
    $cookie = $_COOKIE['remember_me'];

    // --- A. DB 模式 (PDO) ---
    if (isset($pdo)) {
        list($selector, $validator) = explode(':', $cookie . ':');
        if ($selector && $validator) {
            $stmt = $pdo->prepare("SELECT id, user_id, hashed_validator FROM admin_sessions WHERE selector = ? AND expires > NOW() LIMIT 1");
            $stmt->execute([$selector]);
            $auth = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($auth && hash_equals($auth['hashed_validator'], hash('sha256', $validator))) {
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                if (function_exists('soulean_track_session')) soulean_track_session();
                $_SESSION['admin_id'] = $auth['user_id'];
                session_regenerate_id(true);

                // 令牌轮换
                $pdo->prepare("DELETE FROM admin_sessions WHERE id = ?")->execute([$auth['id']]);
                $newSelector = bin2hex(random_bytes(12));
                $newValidator = bin2hex(random_bytes(32));
                $newHashedValidator = hash('sha256', $newValidator);
                $expires = date('Y-m-d H:i:s', time() + 86400 * 30);

                $insert = $pdo->prepare("INSERT INTO admin_sessions (user_id, selector, hashed_validator, expires) VALUES (?, ?, ?, ?)");
                $insert->execute([$auth['user_id'], $newSelector, $newHashedValidator, $expires]);

                setcookie('remember_me', "$newSelector:$newValidator", time() + 86400 * 30, '/', '', isset($_SERVER["HTTPS"]), true);
                header('Location: index.php'); exit;
            }
        }
    }

    // --- B. JSON 模式 (多设备支持) ---
    if (strpos($cookie, 'json_') === 0) {
        $token = substr($cookie, 5);
        $tokens = $data['settings']['remember_tokens'] ?? [];
        $matched = false;
        $currentTime = time();

        foreach ($tokens as $entry) {
            // 格式: [token, created_timestamp]
            if (is_array($entry) && count($entry) >= 2) {
                if (hash_equals($entry[0], $token)) {
                    // 检查有效期 (30天)
                    if ($currentTime - $entry[1] < 86400 * 30) {
                        $matched = true;
                    }
                    break;
                }
            }
        }

        if ($matched) {
            $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                if (function_exists('soulean_track_session')) soulean_track_session();
            $_SESSION['admin_id'] = 1;
            session_regenerate_id(true);
            header('Location: index.php'); exit;
        } else {
            // Token 无效或过期，清除 cookie
            setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);
        }
    }
}

// --- 1. 处理提交 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [Security] CSRF 校验
    if (function_exists('soulean_check_csrf')) {
        soulean_check_csrf();
    }

    // [Security] 速率限制检查
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
        $lastAttempt = $_SESSION['last_attempt_time'] ?? 0;
        if (time() - $lastAttempt < 900) { // 15分钟锁定
            die("<div style='text-align:center;margin-top:50px;font-family:sans-serif;'><h3>🚫 登录尝试次数过多</h3><p>请 15 分钟后再试。</p></div>");
        } else {
            $_SESSION['login_attempts'] = 0; // 解锁
        }
    }

    // 加载配置
    $data = soulean_load_data();
    
    // ==========================================
    // A. 登录逻辑
    // ==========================================
    if ($view === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember_me']); // 获取"记住我"状态
        $loginSuccess = false;

        if (isset($pdo) && $pdo) {
            $stmt = $pdo->prepare("SELECT id, password FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $loginSuccess = true;
                session_regenerate_id(true); // [Security] 防止 Session Fixation
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                if (function_exists('soulean_track_session')) soulean_track_session();
                $_SESSION['admin_id'] = $user['id'];

                // ------------------------------------------------
                // [Security] 设置"记住我" Cookie
                // ------------------------------------------------
                if ($remember) {
                    $selector = bin2hex(random_bytes(12));
                    $validator = bin2hex(random_bytes(32));
                    $hashedValidator = hash('sha256', $validator); // 存哈希，不存明文
                    $expires = date('Y-m-d H:i:s', time() + 86400 * 30); // 30天

                    // 存入数据库
                    $ins = $pdo->prepare("INSERT INTO admin_sessions (user_id, selector, hashed_validator, expires) VALUES (?, ?, ?, ?)");
                    $ins->execute([$user['id'], $selector, $hashedValidator, $expires]);

                    // 设置 Cookie: Selector:Validator
                    // 参数: name, value, expire, path, domain, secure(仅https), httponly(禁止js访问)
                    setcookie('remember_me', "$selector:$validator", time() + 86400 * 30, '/', '', isset($_SERVER["HTTPS"]), true);
                } else {
                    // 如果用户未勾选，且之前有cookie，则清除
                    if (isset($_COOKIE['remember_me'])) {
                        setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);
                    }
                }

                // 登录成功，重置计数器
                unset($_SESSION['login_attempts']);
                header('Location: index.php'); exit;
            }
        }

        // ==========================================
        // [Fallback] JSON 模式认证 (无数据库时)
        // ==========================================
        if (!$loginSuccess && empty($pdo)) {
            $data = soulean_load_data();
            $adminUser = $data['settings']['admin_user'] ?? '';
            $adminPass = $data['settings']['admin_pass'] ?? '';

            // 首次运行：无管理员凭据时，使用提交的凭据创建管理员
            if (!$adminUser && !$adminPass && strlen($username) >= 3 && strlen($password) >= 6) {
                $data['settings']['admin_user'] = $username;
                $data['settings']['admin_pass'] = password_hash($password, PASSWORD_DEFAULT);
                soulean_save_data($data);
                $loginSuccess = true;
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                if (function_exists('soulean_track_session')) soulean_track_session();
                $_SESSION['admin_id'] = 1;
                unset($_SESSION['login_attempts']);
                header('Location: index.php'); exit;
            }

            if ($adminUser && $adminPass && $username === $adminUser && password_verify($password, $adminPass)) {
                $loginSuccess = true;
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                if (function_exists('soulean_track_session')) soulean_track_session();
                $_SESSION['admin_id'] = 1;

                // [Fix] JSON 模式 "remember me" - 支持多设备同时登录
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $rememberTokens = $data['settings']['remember_tokens'] ?? [];

                    // 限制最大10个token，防止无限增长
                    if (count($rememberTokens) > 10) {
                        // 只保留最新的10个
                        $rememberTokens = array_slice($rememberTokens, -10);
                    }

                    $rememberTokens[] = [$token, time()];
                    $data['settings']['remember_tokens'] = $rememberTokens;
                    soulean_save_data($data);

                    setcookie('remember_me', 'json_' . $token, time() + 86400 * 30, '/', '', isset($_SERVER["HTTPS"]), true);
                } else {
                    if (isset($_COOKIE['remember_me'])) {
                        setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);
                    }
                }

                unset($_SESSION['login_attempts']);
                header('Location: index.php'); exit;
            }
        }

        // 登录失败处理
        if (!$loginSuccess) {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['last_attempt_time'] = time();
            sleep(1); // 延时响应防侧信道攻击
            $error = "账号或密码错误。";
        }
    }
    
    // ==========================================
    // B. 发送重置邮件 (保持原样)
    // ==========================================
    elseif ($view === 'forgot') {
        $email = trim($_POST['email']);
        $settings = $data['settings'] ?? [];
        $recoveryEmail = $settings['recovery_email'] ?? '';
        
        sleep(1); 

        if ($email !== $recoveryEmail) {
            $error = "该邮箱不是系统预留的恢复邮箱。";
        } else {
            try {
                $resetToken = bin2hex(random_bytes(16));
            } catch (Exception $e) { 
                $resetToken = md5(uniqid(rand(), true)); 
            }
            
            $expiry = time() + 3600; 
            
            $data['settings']['reset_token'] = $resetToken;
            $data['settings']['reset_expiry'] = $expiry;
            soulean_save_data($data);
            
            $link = (defined('SITE_URL') ? SITE_URL : 'http://'.$_SERVER['HTTP_HOST']) . "/login.php?action=reset&token=$resetToken";
            
            $body = "<div style='background:#f4f4f4;padding:40px 20px;font-family:sans-serif;'>
                        <div style='background:#fff;padding:30px;border-radius:10px;max-width:500px;margin:0 auto;box-shadow:0 4px 15px rgba(0,0,0,0.05);'>
                            <h2 style='color:#333;margin-top:0;font-size:20px;border-bottom:1px solid #eee;padding-bottom:15px;margin-bottom:20px;'>重置您的密码</h2>
                            <p style='color:#555;'>有人申请重置 Soulean 后台管理密码。</p>
                            <p style='text-align:center;margin:35px 0;'>
                                <a href='$link' style='background:#0d9488;color:#fff;padding:12px 28px;text-decoration:none;border-radius:8px;font-weight:bold;display:inline-block;'>点击重置密码</a>
                            </p>
                            <p style='color:#999;font-size:12px;'>链接 1 小时内有效。</p>
                        </div>
                     </div>";
            
            $result = soulean_send_mail($email, "【Soulean】密码重置验证", $body);
            
            if ($result['success']) {
                $success = "重置链接已发送到您的邮箱 ($email)，请查收。";
            } else {
                $error = "邮件发送失败: " . $result['message'];
            }
        }
    }
    
    // ==========================================
    // C. 重置密码 (保持原样)
    // ==========================================
    elseif ($view === 'reset') {
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        $dbToken = $data['settings']['reset_token'] ?? '';
        $dbExpiry = $data['settings']['reset_expiry'] ?? 0;
        
        if (empty($token) || $token !== $dbToken || time() > $dbExpiry) {
            $error = "链接已失效或不正确。";
        } elseif ($newPass !== $confirmPass) {
            $error = "两次输入的密码不一致。";
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE admin_users SET password = ? ORDER BY id ASC LIMIT 1"); 
                $stmt->execute([$newHash]);
                
                // [Security] 重置密码后，清除该用户所有"记住我"的 Session，强制重新登录
                // 假设此时只有一个管理员用户，或者需要根据实际逻辑调整
                $pdo->exec("DELETE FROM admin_sessions");

                unset($data['settings']['reset_token']);
                unset($data['settings']['reset_expiry']);
                soulean_save_data($data);
                
                $success = "密码重置成功！<a href='login.php' class='underline font-bold'>立即登录</a>";
                $view = 'login';
            } else {
                $error = "数据库连接失败，无法更新密码。";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soulean 登录</title>
    <script src="js/tailwind.js"></script>
    <style>body { background-color: #70C1B3; font-family: sans-serif; }</style>
</head>
<body class="flex items-center justify-center h-screen px-4">
    <div class="bg-white/95 backdrop-blur-md p-8 rounded-3xl shadow-2xl w-full max-w-sm text-center">
        <!-- [Fix] 语言选择器 - 使用 GET 链接避免 CSRF 冲突 -->
        <div class="flex justify-end gap-1 mb-2">
            <a href="?set_lang=zh-CN<?php echo $view !== 'login' ? '&action=' . urlencode($view) : ''; ?>" class="px-2 py-1 rounded text-[11px] font-bold <?php echo $currentLang === 'zh-CN' ? 'bg-teal-600 text-white' : 'bg-teal-50 text-teal-600 hover:bg-teal-100'; ?> transition no-underline">简</a>
            <a href="?set_lang=zh-TW<?php echo $view !== 'login' ? '&action=' . urlencode($view) : ''; ?>" class="px-2 py-1 rounded text-[11px] font-bold <?php echo $currentLang === 'zh-TW' ? 'bg-teal-600 text-white' : 'bg-teal-50 text-teal-600 hover:bg-teal-100'; ?> transition no-underline">繁</a>
            <a href="?set_lang=en<?php echo $view !== 'login' ? '&action=' . urlencode($view) : ''; ?>" class="px-2 py-1 rounded text-[11px] font-bold <?php echo $currentLang === 'en' ? 'bg-teal-600 text-white' : 'bg-teal-50 text-teal-600 hover:bg-teal-100'; ?> transition no-underline">EN</a>
        </div>

        <div class="mb-4 text-5xl cursor-default select-none text-teal-600">❖</div>
        <h1 class="text-2xl font-bold text-teal-800 mb-6"><?php echo __('login_title'); ?></h1>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-xs font-bold mb-4 border border-red-100 break-words text-left">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-50 text-green-700 p-3 rounded-lg text-xs font-bold mb-4 border border-green-100 text-left">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- 1. 登录表单 -->
        <?php if ($view === 'login'): ?>
        <form method="POST" class="space-y-4 text-left">
            <?php if(function_exists('soulean_get_csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
            <?php endif; ?>
            
            <div>
                <label class="block text-xs font-bold text-teal-600 mb-1 ml-1"><?php echo __('login_user_label'); ?></label>
                <input type="text" name="username" class="w-full px-4 py-3 rounded-xl border border-teal-100 bg-teal-50 outline-none focus:ring-2 focus:ring-teal-500 transition" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-teal-600 mb-1 ml-1"><?php echo __('login_pass_label'); ?></label>
                <input type="password" name="password" class="w-full px-4 py-3 rounded-xl border border-teal-100 bg-teal-50 outline-none focus:ring-2 focus:ring-teal-500 transition" required>
            </div>

            <!-- 记住我复选框 -->
            <div class="flex items-center ml-1">
                <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded cursor-pointer">
                <label for="remember_me" class="ml-2 block text-xs text-gray-500 cursor-pointer select-none">
                    <?php echo __('login_remember'); ?>
                </label>
            </div>
            <button type="submit" class="w-full bg-teal-600 text-white py-3 rounded-xl font-bold hover:bg-teal-700 shadow-lg shadow-teal-200 mt-2 transition transform active:scale-95"><?php echo __('login_submit'); ?></button>
            <div class="text-center mt-4"><a href="?action=forgot" class="text-xs text-teal-500 hover:text-teal-700 transition"><?php echo __('login_forgot'); ?></a></div>
        </form>
        
        <!-- 2. 忘记密码表单 -->
        <?php elseif ($view === 'forgot'): ?>
        <form method="POST" class="space-y-4 text-left">
            <?php if(function_exists('soulean_get_csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
            <?php endif; ?>
            <p class="text-xs text-gray-500 mb-2"><?php echo __('forgot_desc'); ?></p>
            <div>
                <label class="block text-xs font-bold text-teal-600 mb-1 ml-1"><?php echo __('login_user_label'); ?></label>
                <input type="email" name="email" class="w-full px-4 py-3 rounded-xl border border-teal-100 bg-teal-50 outline-none focus:ring-2 focus:ring-teal-500 transition" required>
            </div>
            
            <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-xl font-bold hover:bg-blue-600 shadow-lg shadow-blue-200 mt-2 transition transform active:scale-95"><?php echo __('forgot_submit'); ?></button>
            <div class="text-center mt-4"><a href="?action=login" class="text-xs text-gray-400 hover:text-gray-600 transition">← <?php echo __('login_back'); ?></a></div>
        </form>

        <!-- 3. 重置密码表单 -->
        <?php elseif ($view === 'reset'): ?>
        <form method="POST" class="space-y-4 text-left">
            <?php if(function_exists('soulean_get_csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?php echo soulean_get_csrf_token(); ?>">
            <?php endif; ?>
            <p class="text-xs text-gray-500 mb-2"><?php echo __('reset_title'); ?></p>
            <div>
                <label class="block text-xs font-bold text-teal-600 mb-1 ml-1"><?php echo __('login_pass_label'); ?></label>
                <input type="password" name="new_password" class="w-full px-4 py-3 rounded-xl border border-teal-100 bg-teal-50 outline-none focus:ring-2 focus:ring-teal-500 transition" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-teal-600 mb-1 ml-1"><?php echo __('reset_title'); ?></label>
                <input type="password" name="confirm_password" class="w-full px-4 py-3 rounded-xl border border-teal-100 bg-teal-50 outline-none focus:ring-2 focus:ring-teal-500 transition" required>
            </div>
            
            <button type="submit" class="w-full bg-teal-600 text-white py-3 rounded-xl font-bold hover:bg-teal-700 shadow-lg shadow-teal-200 mt-2 transition transform active:scale-95"><?php echo __('reset_submit'); ?></button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
