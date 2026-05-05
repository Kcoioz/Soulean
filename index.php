<?php
// 检查配置文件
if (!file_exists('config.php')) {
    die("找不到 config.php");
}
require 'config.php';

// --- 1. 读取数据 
$data = soulean_load_data();
$settings = $data['settings'] ?? [];

// --- 2. 权限控制 (Public Mode Logic) ---
// 检查是否已登录
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
// 检查是否开启了公开模式
$isPublicMode = !empty($settings['public_mode']);

// 如果既没登录，又没开启公开模式 -> 跳转登录
if (!$isLoggedIn && !$isPublicMode) {
    header('Location: login.php');
    exit;
}

// --- 3. SEO 设置 ---
$siteTitle = htmlspecialchars($settings['site_title'] ?? 'Soulean 清心');
$siteKeywords = htmlspecialchars($settings['site_keywords'] ?? '自律,戒色,清心');
$siteDesc = htmlspecialchars($settings['site_description'] ?? '重塑神经回路');

// 读取语言设置
$currentLang = $settings['language'] ?? 'zh-CN';
$frontendDict = require 'dictionary/frontend.php';
$langData = $frontendDict[$currentLang] ?? $frontendDict['zh-CN'];

// 向前端传递登录状态、CSRF Token 与语言包
$jsConfig = json_encode([
    'isLoggedIn' => $isLoggedIn,
    'csrfToken' => soulean_get_csrf_token(),
    'language' => $currentLang,
    'dictionary' => $langData
]);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang === 'en' ? 'en' : 'zh-CN'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/x-icon" href="./favicon.ico" />
    
    <!-- 动态 SEO -->
    <title><?php echo $siteTitle; ?></title>
    <meta name="keywords" content="<?php echo $siteKeywords; ?>">
    <meta name="description" content="<?php echo $siteDesc; ?>">
    
    <!-- 基础库 -->
    <script src="js/react.production.min.js"></script>
    <script src="js/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="js/tailwind.js"></script>
    
    <!-- 加载样式 -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- 注入配置 -->
    <script>window.SouleanConfig = <?php echo $jsConfig; ?>;</script>
    <script>
        window.__ = function(key) {
            return (window.SouleanConfig && window.SouleanConfig.dictionary && window.SouleanConfig.dictionary[key]) || key;
        };
        window.getLang = function() { return (window.SouleanConfig && window.SouleanConfig.language) || 'zh-CN'; };
    </script>
</head>
<body>
    <div id="root">
        <div style="height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; color: white;">
            <div class="spinner"></div>
            <p style="margin-top: 20px; font-size: 14px; opacity: 0.8;" id="loading-text">正在初始化...</p>
        </div>
    </div>

    <!-- 错误捕捉 -->
    <script>
        window.onerror = function(msg, url, line) {
            if (msg === 'Script error.' && !url) return;
            var errTitle = window.__ ? window.__('program_error') : '程序出错';
            document.getElementById('root').innerHTML = 
                '<div style="padding:20px; color:white; text-align:center;">' +
                '<h3 style="font-size:20px; margin-bottom:10px;">⚠️ ' + errTitle + '</h3>' +
                '<p style="background:rgba(0,0,0,0.2); padding:10px; border-radius:8px; font-family:monospace; font-size:12px;">' + msg + '</p>' +
                '</div>';
        };
    </script>

    <!-- 模块化加载 JS (按依赖顺序) -->
    <script type="text/babel" src="js/components.js"></script>
    <script type="text/babel" src="js/analysis.js"></script>
    <script type="text/babel" src="js/record-browser.js"></script>
    <script type="text/babel" src="js/hooks.js"></script>
    <script type="text/babel" src="js/app.js"></script>
</body>
</html>