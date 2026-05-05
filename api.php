<?php
/**
 * api.php
 */

require 'config.php';

// 1. 安全头与 CORS 设置
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// 处理 OPTIONS 预检
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$data = soulean_load_data();
$isPublicMode = !empty($data['settings']['public_mode']);
$action = isset($_GET['action']) ? $_GET['action'] : '';

// CSRF 检查
function checkCSRF() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        // 1. 检查 CSRF Token (从 Header 获取)
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
             http_response_code(403);
             echo json_encode(['error' => 'CSRF token validation failed.']);
             exit;
        }

        // 2. 检查 Content-Type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid Content-Type. Expected application/json']);
             exit;
        }
    }
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function getDailyGrowthRate($score) {
    $score = floatval($score);
    if ($score >= 90) return 1.6;
    if ($score >= 80) return 1.4;
    if ($score >= 70) return 1.1;
    if ($score >= 60) return 0.5;
    if ($score >= 50) return 0.35;
    return 0.25;
}

switch ($action) {
    case 'get_data':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        if (!$isLoggedIn && !$isPublicMode) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
        
        $data = processDailyGrowth($data); 
        
        $responseData = $data;
        if (!$isLoggedIn) {
            $responseData = [
                'score' => $data['score'],
                'start_date' => $data['start_date'],
                'logs' => $data['logs'],
                'analysis' => $data['analysis'] ?? [], // 公开分析数据
                'settings' => [
                    'site_title' => $data['settings']['site_title'] ?? '',
                    'site_description' => $data['settings']['site_description'] ?? '',
                    'public_mode' => $data['settings']['public_mode'] ?? 0
                ]
            ];
        } else {
            unset($responseData['settings']['smtp_pass']); 
        }
        echo json_encode($responseData);
        break;

    case 'log':
        // ... (CSRF 检查) ...
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        checkCSRF();
        if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'Read-only']); exit; }

        $input = json_decode(file_get_contents('php://input'), true);
        $type = isset($input['type']) ? trim(strip_tags($input['type'])) : '';
        
        // [Security] 严格校验类型
        $allowedTypes = ['urge', 'porn', 'relapse', 'sex', 'emission', 'energy'];
        if (!in_array($type, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type']);
            exit;
        }

        $reason = isset($input['reason']) ? trim(strip_tags($input['reason'])) : '';
        $energy = isset($input['energy']) ? intval($input['energy']) : null;

        $data = soulean_load_data();
        $data = processDailyGrowth($data);
        $todayStr = date('Y-m-d');
        if (!isset($data['logs'][$todayStr])) $data['logs'][$todayStr] = [];

        $pointMap = $data['settings']['points'] ?? [];
        if ($type === 'energy') {
            if ($energy >= 1 && $energy <= 10) {
                $data['analysis']['energy_logs'][$todayStr] = $energy;
            }
        } elseif ($type === 'urge') {
            $data['score'] -= floatval($pointMap['urge'] ?? 0.01);
            $data['logs'][$todayStr][] = 'urge';
        } elseif ($type === 'porn') {
            $data['score'] -= floatval($pointMap['porn'] ?? 1.0);
            $data['logs'][$todayStr][] = 'porn';
        } elseif ($type === 'emission') {
            $data['score'] -= floatval($pointMap['emission'] ?? 2.0);
            $data['logs'][$todayStr][] = 'emission';
        } elseif ($type === 'relapse') {
            $data['score'] -= floatval($pointMap['relapse'] ?? 3.0);
            $data['start_date'] = $todayStr;
            $data['logs'][$todayStr][] = 'relapse';

            if (!empty($reason)) {
                $data['analysis']['relapse_records'][] = [
                    'date' => $todayStr,
                    'reason' => $reason
                ];
            }
        } elseif ($type === 'sex') {
            $data['score'] -= floatval($pointMap['sex'] ?? 3.0);
            $data['start_date'] = $todayStr;
            $data['logs'][$todayStr][] = 'sex';
        }

        $data['score'] = max(0, round($data['score'], 2));

        if (soulean_save_data($data)) {
            echo json_encode(['status' => 'success', 'score' => $data['score']]);
        } else {
            http_response_code(500); echo json_encode(['error' => 'Save failed']);
        }
        break;

    case 'delete_log':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        checkCSRF();
        if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'Permission denied']); exit; }

        $input = json_decode(file_get_contents('php://input'), true);
        $dateToDelete = isset($input['date']) ? trim(strip_tags($input['date'])) : '';
        $typeToDelete = isset($input['type']) ? trim(strip_tags($input['type'])) : '';

        if (!$dateToDelete || !validateDate($dateToDelete)) { http_response_code(400); echo json_encode(['error' => 'Invalid date']); exit; }

        // [Security] 严格校验类型
        $allowedTypes = ['urge', 'porn', 'relapse', 'sex', 'emission'];
        if (!in_array($typeToDelete, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type']);
            exit;
        }

        $data = soulean_load_data();

        // [Fix] 先运行 daily growth 确保数据一致性，避免删除后分数被 grow 覆盖
        $data = processDailyGrowth($data);

        if (isset($data['logs'][$dateToDelete]) && is_array($data['logs'][$dateToDelete])) {
            $types = $data['logs'][$dateToDelete];
            $index = array_search($typeToDelete, $types);
            if ($index !== false) {
                array_splice($data['logs'][$dateToDelete], $index, 1);
                if (empty($data['logs'][$dateToDelete])) unset($data['logs'][$dateToDelete]);

                // [Fix] 记录当前分数以备日志
                $oldScore = $data['score'];

                $pointMap = $data['settings']['points'] ?? [];
                if ($typeToDelete === 'urge') $data['score'] += floatval($pointMap['urge'] ?? 0.01);
                elseif ($typeToDelete === 'porn') $data['score'] += floatval($pointMap['porn'] ?? 1.0);
                elseif ($typeToDelete === 'emission') $data['score'] += floatval($pointMap['emission'] ?? 2.0);
                elseif ($typeToDelete === 'relapse') $data['score'] += floatval($pointMap['relapse'] ?? 3.0);
                elseif ($typeToDelete === 'sex') $data['score'] += floatval($pointMap['sex'] ?? 3.0);

                // [Fix] 四舍五入避免浮点精度问题
                $data['score'] = min(100, round($data['score'], 2));

                if ($typeToDelete === 'relapse' || $typeToDelete === 'sex') {
                    // 1. 重新计算起始日逻辑
                    $allBreakDates = [];
                    foreach ($data['logs'] as $d => $ts) {
                        if (!is_array($ts)) continue;
                        if (in_array('relapse', $ts) || in_array('sex', $ts)) $allBreakDates[] = $d;
                    }
                    if (count($allBreakDates) > 0) {
                        rsort($allBreakDates);
                        $data['start_date'] = $allBreakDates[0];
                    } else {
                        // 删除最后一次破戒记录后，应从当天重新开始累计
                        $data['start_date'] = date('Y-m-d');
                    }

                    // [Fix] 当 start_date 被重置到更近的日期时，last_update 不需要特殊处理
                    // processDailyGrowth 已经确保了 last_update = today

                    // 2. 删除破戒时，同步删除分析数据中的对应记录
                    if ($typeToDelete === 'relapse' && isset($data['analysis']['relapse_records'])) {
                        $newRecords = [];
                        $deletedOne = false;
                        foreach ($data['analysis']['relapse_records'] as $record) {
                            if (!$deletedOne && $record['date'] === $dateToDelete) {
                                $deletedOne = true;
                                continue;
                            }
                            $newRecords[] = $record;
                        }
                        $data['analysis']['relapse_records'] = $newRecords;
                    }
                }

                if (soulean_save_data($data)) {
                    echo json_encode(['status' => 'success', 'score' => $data['score']]);
                } else {
                    echo json_encode(['error' => 'Save failed']);
                }
            } else echo json_encode(['error' => 'Not found']);
        } else echo json_encode(['error' => 'No logs']);
        break;

    case 'logout':
        // [Fix] 清除当前设备的 remember_me cookie 和 token，不影响其他设备
        if (!empty($_COOKIE['remember_me'])) {
            $cookie = $_COOKIE['remember_me'];

            // JSON 模式：从 data.json 移除当前 token
            if (strpos($cookie, 'json_') === 0) {
                $token = substr($cookie, 5);
                $data = soulean_load_data();
                $tokens = $data['settings']['remember_tokens'] ?? [];
                $newTokens = [];
                foreach ($tokens as $entry) {
                    if (is_array($entry) && count($entry) >= 2) {
                        // 保留其他设备的 token，只移除当前匹配的
                        if (!hash_equals($entry[0], $token)) {
                            $newTokens[] = $entry;
                        }
                    }
                }
                $data['settings']['remember_tokens'] = $newTokens;
                soulean_save_data($data);
            }

            // DB 模式：从 admin_sessions 移除当前 token
            if (isset($pdo) && strpos($cookie, ':') !== false) {
                list($selector) = explode(':', $cookie);
                if (!empty($selector)) {
                    $stmt = $pdo->prepare("DELETE FROM admin_sessions WHERE selector = ?");
                    $stmt->execute([$selector]);
                }
            }

            // 清除 cookie
            setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);
        }

        session_destroy();
        echo json_encode(['status' => 'success']);
        break;

    case 'send_report':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        checkCSRF();
        if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'Login required']); exit; }
        
        $targetEmail = $data['settings']['recovery_email'] ?? '';
        if (empty($targetEmail)) {
            http_response_code(400);
            echo json_encode(['error' => 'no_email']);
            exit;
        }
        
        $subject = "【Soulean】即時修養報告 - " . date('Y-m-d');
        $body = soulean_generate_report_html($data);
        $result = soulean_send_mail($targetEmail, $subject, $body);
        
        if ($result['success']) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'send_failed', 'detail' => $result['message'] ?? 'Unknown error']);
        }
        break;

    case 'save_note':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        checkCSRF();
        if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'Login required']); exit; }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $note = isset($input['note']) ? trim(strip_tags($input['note'])) : '';
        $todayStr = date('Y-m-d');
        
        $data = soulean_load_data(); // 确保加载最新数据
        $data['analysis']['daily_notes'] = $data['analysis']['daily_notes'] ?? [];
        $data['analysis']['daily_notes'][$todayStr] = $note;
        
        if (soulean_save_data($data)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Save failed']);
        }
        break;

    case 'delete_note':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        checkCSRF();
        if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'Login required']); exit; }

        $input = json_decode(file_get_contents('php://input'), true);
        $noteDate = isset($input['date']) ? trim(strip_tags($input['date'])) : '';

        if (!$noteDate || !validateDate($noteDate)) { http_response_code(400); echo json_encode(['error' => 'Invalid date']); exit; }

        if (soulean_delete_note($noteDate)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed']);
        }
        break;

    case 'set_language':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }
        checkCSRF();
        $input = json_decode(file_get_contents('php://input'), true);
        $lang = isset($input['lang']) ? trim(strip_tags($input['lang'])) : 'zh-CN';
        $allowedLangs = ['zh-CN', 'zh-TW', 'en'];
        if (!in_array($lang, $allowedLangs)) { http_response_code(400); echo json_encode(['error' => 'Invalid language']); exit; }

        $_SESSION['lang'] = $lang; 
        if ($isLoggedIn) {
            $data = soulean_load_data();
            $data['settings']['language'] = $lang;
            soulean_save_data($data);
        }
        echo json_encode(['status' => 'success']);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Invalid action']); break;
}

function processDailyGrowth($data) {
    $today = date('Y-m-d');
    $lastUpdate = isset($data['last_update']) ? $data['last_update'] : $today;
    
    if ($today > $lastUpdate) {
        $d1 = new DateTime($lastUpdate);
        $d2 = new DateTime($today);
        $diff = $d1->diff($d2);
        $daysPassed = $diff->days;

        if ($daysPassed > 0) {
            $growthRate = getDailyGrowthRate($data['score'] ?? 0);
            $growth = $daysPassed * $growthRate;
            $data['score'] += $growth;
            $data['last_update'] = $today;
            $data['score'] = min(100, round($data['score'], 2));
            soulean_save_data($data); 
        }
    }
    return $data;
}
?>
