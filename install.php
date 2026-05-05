<?php
/**
 * install.php - Soulean 系统安装程序 (合并增强版)
 * 集成功能：多模式存储支持、持久化 Session 表、安全防护、自动删除
 */
session_start();

// --- 多语言 ---
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strpos($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'zh') !== false ? 'zh-CN' : 'en');
if (isset($_GET['lang'])) $_SESSION['lang'] = $lang;
$dict = [
    'zh-CN' => [
        'installed_title' => '系统已安装',
        'installed_desc' => '为了安全起见，安装程序已被锁定。',
        'installed_reinstall' => '如需重装，请手动删除 <code>db_config.php</code> 文件。',
        'installed_home' => '返回首页',
        'error_nowritable' => '当前目录不可写，无法生成配置文件。请修改目录权限 (chmod 755 或 777)。',
        'error_dbname' => '数据库名格式错误：仅允许字母、数字和下划线。',
        'error_noadmin' => '请设置管理员账号和密码。',
        'error_writeconfig' => '配置文件写入失败，请检查目录权限。',
        'error_install' => '安装失败',
        'success_title' => '安装成功！模式',
        'success_redirect' => '正在跳转至登录页...',
        'page_title' => 'Soulean 安装向导',
        'page_heading' => 'Soulean 安装',
        'label_storage' => '存储模式',
        'mode_json' => 'JSON 混合模式 (管理在库, 数据在文件)',
        'mode_mysql' => 'MySQL 纯净模式 (全量入库)',
        'mode_sqlite' => 'SQLite 3 (单文件数据库)',
        'label_mysql_config' => 'MySQL 配置',
        'ph_host' => '主机 (localhost)',
        'ph_dbname' => '数据库名',
        'ph_port' => '端口 (3306)',
        'ph_user' => '用户名',
        'ph_dbpass' => '数据库密码',
        'label_admin' => '设置管理员',
        'ph_admin_user' => '管理员账号',
        'ph_admin_pass' => '登录密码',
        'btn_install' => '立即安装',
        'btn_disabled' => '目录不可写',
        'label_lang' => '语言',
    ],
    'zh-TW' => [
        'installed_title' => '系統已安裝',
        'installed_desc' => '為了安全起見，安裝程式已被鎖定。',
        'installed_reinstall' => '如需重裝，請手動刪除 <code>db_config.php</code> 檔案。',
        'installed_home' => '返回首頁',
        'error_nowritable' => '目前目錄不可寫，無法生成設定檔。請修改目錄權限 (chmod 755 或 777)。',
        'error_dbname' => '資料庫名稱格式錯誤：僅允許字母、數字和底線。',
        'error_noadmin' => '請設定管理員帳號和密碼。',
        'error_writeconfig' => '設定檔寫入失敗，請檢查目錄權限。',
        'error_install' => '安裝失敗',
        'success_title' => '安裝成功！模式',
        'success_redirect' => '正在跳轉至登入頁...',
        'page_title' => 'Soulean 安裝精靈',
        'page_heading' => 'Soulean 安裝',
        'label_storage' => '儲存模式',
        'mode_json' => 'JSON 混合模式 (管理在庫, 資料在檔案)',
        'mode_mysql' => 'MySQL 純淨模式 (全量入庫)',
        'mode_sqlite' => 'SQLite 3 (單檔案資料庫)',
        'label_mysql_config' => 'MySQL 設定',
        'ph_host' => '主機 (localhost)',
        'ph_dbname' => '資料庫名稱',
        'ph_port' => '埠號 (3306)',
        'ph_user' => '使用者名稱',
        'ph_dbpass' => '資料庫密碼',
        'label_admin' => '設定管理員',
        'ph_admin_user' => '管理員帳號',
        'ph_admin_pass' => '登入密碼',
        'btn_install' => '立即安裝',
        'btn_disabled' => '目錄不可寫',
        'label_lang' => '語言',
    ],
    'en' => [
        'installed_title' => 'System Already Installed',
        'installed_desc' => 'For security reasons, the installer has been locked.',
        'installed_reinstall' => 'To reinstall, manually delete the <code>db_config.php</code> file.',
        'installed_home' => 'Back to Home',
        'error_nowritable' => 'Directory is not writable. Cannot generate config file. Please modify permissions (chmod 755 or 777).',
        'error_dbname' => 'Invalid database name: only letters, numbers, and underscores are allowed.',
        'error_noadmin' => 'Please set an admin username and password.',
        'error_writeconfig' => 'Failed to write config file. Please check directory permissions.',
        'error_install' => 'Installation Failed',
        'success_title' => 'Installation Successful! Mode',
        'success_redirect' => 'Redirecting to login page...',
        'page_title' => 'Soulean Setup Wizard',
        'page_heading' => 'Soulean Setup',
        'label_storage' => 'Storage Mode',
        'mode_json' => 'JSON Hybrid Mode (admin in DB, data in file)',
        'mode_mysql' => 'MySQL Pure Mode (all data in DB)',
        'mode_sqlite' => 'SQLite 3 (single-file database)',
        'label_mysql_config' => 'MySQL Configuration',
        'ph_host' => 'Host (localhost)',
        'ph_dbname' => 'Database Name',
        'ph_port' => 'Port (3306)',
        'ph_user' => 'Username',
        'ph_dbpass' => 'Database Password',
        'label_admin' => 'Admin Account',
        'ph_admin_user' => 'Admin Username',
        'ph_admin_pass' => 'Password',
        'btn_install' => 'Install Now',
        'btn_disabled' => 'Directory not writable',
        'label_lang' => 'Language',
    ],
];
function __install($key) {
    global $lang, $dict;
    return $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
}

// 0. 环境预检查
$isWritable = is_writable(__DIR__);

// [Security] 防止二次安装
if (file_exists('db_config.php')) {
    die("<div style='font-family:sans-serif;text-align:center;padding:50px;color:#444;'>
            <h1>🚫 " . __install('installed_title') . "</h1>
            <p>" . __install('installed_desc') . "</p>
            <p>" . __install('installed_reinstall') . "</p>
            <p><a href='index.php' style='color:#0d9488'>" . __install('installed_home') . "</a></p>
         </div>");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isWritable) {
        $error = __install('error_nowritable');
    } else {
        $storage_mode = $_POST['storage_mode'] ?? 'json';
        $admin_user = trim($_POST['admin_user']);
        $admin_pass = $_POST['admin_pass'];

        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_name = $_POST['db_name'] ?? 'soulean';
        $db_user = $_POST['db_user'] ?? 'root';
        $db_pass = $_POST['db_pass'] ?? '';
        $db_port = $_POST['db_port'] ?? '3306';

        // [Security] 严格校验数据库名
        if (($storage_mode === 'mysql' || $storage_mode === 'json') && !preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
            $error = __install('error_dbname');
        } elseif (empty($admin_user) || empty($admin_pass)) {
            $error = __install('error_noadmin');
        } else {
            try {
                $pdo = null;
                $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);

                // 1. 自动生成 .htaccess 安全防护
                if (!file_exists('.htaccess')) {
                    $htaccessContent = "<FilesMatch \"\.(json|db|lock|sqlite|sqlite3)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n<FilesMatch \"^(config|db_config)\.php$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>";
                    @file_put_contents('.htaccess', $htaccessContent);
                }

                // 2. 根据模式初始化数据库连接
                if ($storage_mode === 'sqlite') {
                    $dbFile = __DIR__ . '/soulean.db';
                    $pdo = new PDO("sqlite:" . $dbFile);
                } else {
                    // MySQL 环境 (JSON模式也需要MySQL存储管理员信息)
                    $dsn = "mysql:host=$db_host;port=$db_port;charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$db_name`");
                }
                
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // 3. 执行建表逻辑
                createAdminTables($pdo, $storage_mode === 'sqlite' ? 'sqlite' : 'mysql');
                
                if ($storage_mode === 'mysql' || $storage_mode === 'sqlite') {
                    createDataTables($pdo, $storage_mode === 'sqlite' ? 'sqlite' : 'mysql');
                    initData($pdo);
                }

                // 4. 初始化管理员
                initAdmin($pdo, $admin_user, $hashed_pass);

                // 5. 如果是 JSON 混合模式，初始化文件
                if ($storage_mode === 'json') {
                    if (!file_exists('data.json')) {
                        $initialData = [
                            'score' => 80,
                            'start_date' => date('Y-m-d'),
                            'last_update' => date('Y-m-d'),
                            'settings' => [
                                'site_title' => 'Soulean 清心',
                                'admin_user' => $admin_user,
                                'admin_pass' => $hashed_pass
                            ],
                            'logs' => [],
                            'analysis' => [
                                'relapse_records' => [],
                                'energy_logs' => [],
                                'daily_notes' => []
                            ]
                        ];
                        file_put_contents('data.json', json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }

                // 6. 生成配置文件
                $isSqlite = ($storage_mode === 'sqlite');
                $configContent = "<?php\n"
                               . "// 自动生成的数据库配置文件\n"
                               . "// 生成时间: " . date('Y-m-d H:i:s') . "\n\n"
                               . "define('DB_HOST', " . var_export($isSqlite ? '' : $db_host, true) . ");\n"
                               . "define('DB_NAME', " . var_export($isSqlite ? '' : $db_name, true) . ");\n"
                               . "define('DB_USER', " . var_export($isSqlite ? '' : $db_user, true) . ");\n"
                               . "define('DB_PASS', " . var_export($isSqlite ? '' : $db_pass, true) . ");\n"
                               . "define('DB_PORT', " . var_export($isSqlite ? '' : $db_port, true) . ");\n"
                               . "define('STORAGE_MODE', " . var_export($storage_mode, true) . ");\n"
                               . "define('DEBUG_MODE', false);\n\n"
                               . "// PDO 实例化辅助逻辑 (可选)\n"
                               . "if (STORAGE_MODE !== 'json' || DB_HOST !== '') {\n"
                               . "    \$dsn = STORAGE_MODE === 'sqlite' ? 'sqlite:' . __DIR__ . '/soulean.db' : \"mysql:host=\".DB_HOST.\";dbname=\".DB_NAME.\";port=\".DB_PORT.\";charset=utf8mb4\";\n"
                               . "    try {\n"
                               . "        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);\n"
                               . "    } catch (PDOException \$e) {}\n"
                               . "}\n"
                               . "?>";

                if (file_put_contents('db_config.php', $configContent)) {
                    $success = __install('success_title') . ": " . strtoupper($storage_mode);
                    @unlink(__FILE__);
                    echo "<meta http-equiv='refresh' content='2;url=login.php'>";
                } else {
                    $error = __install('error_writeconfig');
                }

            } catch (Exception $e) {
                $error = __install('error_install') . ": " . $e->getMessage();
            }
        }
    }
}

// --- 辅助函数 ---

function createAdminTables($pdo, $type) {
    $isMy = ($type === 'mysql');
    $pk = $isMy ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
    $engine = $isMy ? "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" : "";

    // 管理员表
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id $pk,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $engine");

    // 持久化 Session 表
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
        id $pk,
        user_id INT NOT NULL,
        selector CHAR(24) NOT NULL,
        hashed_validator CHAR(64) NOT NULL,
        expires DATETIME NOT NULL
    ) $engine");
    
    if ($isMy) {
        try { $pdo->exec("CREATE INDEX idx_selector ON admin_sessions(selector)"); } catch(Exception $e){}
    }
}

function createDataTables($pdo, $type) {
    $isMy = ($type === 'mysql');
    $pk = $isMy ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
    $engine = $isMy ? "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" : "";

    // 核心数据表
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        start_date DATE NOT NULL,
        score DECIMAL(10, 2) DEFAULT 80.00,
        last_update_date DATE NOT NULL,
        settings TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $engine");

    // 日志表
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_logs (
        id $pk,
        log_date DATE NOT NULL,
        log_type VARCHAR(20) NOT NULL,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $engine");

    if ($isMy) {
        try { $pdo->exec("CREATE INDEX idx_log_date ON daily_logs(log_date)"); } catch(Exception $e){}
    }
}

function initAdmin($pdo, $user, $passHash) {
    $pdo->exec("DELETE FROM admin_users");
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
    $stmt->execute([$user, $passHash]);
}

function initData($pdo) {
    $count = $pdo->query("SELECT count(*) FROM users")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (start_date, score, last_update_date, settings) VALUES (?, 80.00, ?, '{}')");
        $today = date('Y-m-d');
        $stmt->execute([$today, $today]);
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __install('page_title'); ?></title>
    <script src="js/tailwind.js"></script>
    <style>
        body { background-color: #f0fdfa; font-family: sans-serif; }
        .input-field { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; outline: none; transition: 0.2s; }
        .input-field:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
    </style>
    <script>
        function toggleDbFields(mode) {
            const mysqlFields = document.getElementById('mysql-config-section');
            mysqlFields.style.display = (mode === 'sqlite') ? 'none' : 'block';
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-teal-100">
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-teal-600 text-white rounded-xl flex items-center justify-center text-2xl font-bold mx-auto mb-4">S</div>
            <h1 class="text-2xl font-bold text-teal-800"><?php echo __install('page_heading'); ?></h1>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 text-green-700 p-6 rounded-xl text-center border border-green-100">
                <div class="text-4xl mb-2">🎉</div>
                <p class="font-bold text-lg"><?php echo $success; ?></p>
                <p class="text-sm opacity-70"><?php echo __install('success_redirect'); ?></p>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm border border-red-100">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div class="flex justify-end gap-2">
                    <span class="text-xs text-gray-400 self-center"><?php echo __install('label_lang'); ?>:</span>
                    <a href="?lang=zh-CN" class="text-xs px-2 py-1 rounded <?php echo $lang==='zh-CN'?'bg-teal-100 text-teal-700 font-bold':'text-gray-400'; ?>">简</a>
                    <a href="?lang=zh-TW" class="text-xs px-2 py-1 rounded <?php echo $lang==='zh-TW'?'bg-teal-100 text-teal-700 font-bold':'text-gray-400'; ?>">繁</a>
                    <a href="?lang=en" class="text-xs px-2 py-1 rounded <?php echo $lang==='en'?'bg-teal-100 text-teal-700 font-bold':'text-gray-400'; ?>">EN</a>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <h3 class="text-xs font-bold text-slate-500 uppercase mb-3"><?php echo __install('label_storage'); ?></h3>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="storage_mode" value="json" onclick="toggleDbFields('json')" checked class="accent-teal-600">
                            <span class="text-sm text-gray-700"><?php echo __install('mode_json'); ?></span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="storage_mode" value="mysql" onclick="toggleDbFields('mysql')" class="accent-teal-600">
                            <span class="text-sm text-gray-700"><?php echo __install('mode_mysql'); ?></span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="storage_mode" value="sqlite" onclick="toggleDbFields('sqlite')" class="accent-teal-600">
                            <span class="text-sm text-gray-700"><?php echo __install('mode_sqlite'); ?></span>
                        </label>
                    </div>
                </div>

                <div id="mysql-config-section" class="space-y-3 bg-blue-50 p-4 rounded-xl border border-blue-100">
                    <h3 class="text-xs font-bold text-blue-600 uppercase mb-2"><?php echo __install('label_mysql_config'); ?></h3>
                    <input type="text" name="db_host" placeholder="<?php echo __install('ph_host'); ?>" value="localhost" class="input-field text-sm">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="db_name" placeholder="<?php echo __install('ph_dbname'); ?>" value="soulean" class="input-field text-sm">
                        <input type="text" name="db_port" placeholder="<?php echo __install('ph_port'); ?>" value="3306" class="input-field text-sm">
                    </div>
                    <input type="text" name="db_user" placeholder="<?php echo __install('ph_user'); ?>" value="root" class="input-field text-sm">
                    <input type="password" name="db_pass" placeholder="<?php echo __install('ph_dbpass'); ?>" class="input-field text-sm">
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-gray-400 uppercase"><?php echo __install('label_admin'); ?></h3>
                    <input type="text" name="admin_user" placeholder="<?php echo __install('ph_admin_user'); ?>" class="input-field" required>
                    <input type="password" name="admin_pass" placeholder="<?php echo __install('ph_admin_pass'); ?>" class="input-field" required>
                </div>

                <button type="submit" <?php echo !$isWritable ? 'disabled' : ''; ?> class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl transition active:scale-95 disabled:opacity-50">
                    <?php echo $isWritable ? __install('btn_install') : __install('btn_disabled'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>