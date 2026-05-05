<?php
/**
 * cron.php - 自动化任务脚本
 * 依赖 config.php 中的 soulean_generate_report_html
 */

require 'config.php';

$secretKey = soulean_get_cron_secret();
$requestKey = $_GET['key'] ?? '';
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // [Security] 使用 hash_equals 防止时序攻击
    if (empty($requestKey) || !hash_equals($secretKey, $requestKey)) {
        sleep(1); http_response_code(403); die("Access Denied");
    }
}

soulean_update_cron_time();
$data = soulean_load_data();
$settings = $data['settings'];

$targetEmail = $settings['recovery_email'] ?? '';

if (empty($targetEmail)) die("Target Email Not Configured.");

// 手动触发
if (isset($_GET['action']) && $_GET['action'] === 'send_report_now') {
    $result = soulean_send_mail($targetEmail, "【Soulean】即时统计报告", soulean_generate_report_html($data));
    if ($result['success']) {
        echo "Report Sent OK.";
    } else {
        echo "Failed: " . $result['message'];
    }
    exit;
}

// 每日提醒逻辑
$today = date('Y-m-d');
$hasLoggedToday = !empty($data['logs'][$today]) || isset($data['analysis']['energy_logs'][$today]);
$isForce = isset($_GET['force_remind']); // 强制模式

// [Logic Fix] 如果开启了强制模式，则跳过"今日已打卡"检查
if ($hasLoggedToday && !$isForce) {
    echo "User has logged today.";
} else {
    $reminders = $settings['reminders'] ?? [];
    $shouldRemind = false;
    foreach ($reminders as $time) {
        // 检查当前时间是否在设定时间的 10 分钟窗口内
        if (date('H:i') >= $time && date('H:i') < date('H:i', strtotime($time . ' +10 minutes'))) {
             $cacheFile = __DIR__ . '/last_remind.txt';
             $lastSent = file_exists($cacheFile) ? file_get_contents($cacheFile) : '';
             if ($lastSent !== $today . '_' . $time) {
                 $shouldRemind = true;
                 file_put_contents($cacheFile, $today . '_' . $time);
             }
        }
    }

    // 如果满足提醒条件，或者强制测试
    if ($shouldRemind || $isForce) {
        $link = SITE_URL;
        $body = "<div style='padding:20px;text-align:center;background:#f3f4f6;'><div style='background:white;padding:30px;border-radius:10px;display:inline-block;'><h2 style='color:#0d9488;'>🌱 保持觉知</h2><p>今天还没有打卡哦。</p><a href='$link' style='background:#0d9488;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>去打卡</a></div></div>";
        
        $result = soulean_send_mail($targetEmail, "【Soulean】打卡提醒", $body);
        
        if ($result['success']) echo "Reminder Sent.";
        else echo "Reminder Failed: " . $result['message'];
    } else {
        echo "No reminder needed now.";
    }
}
?>