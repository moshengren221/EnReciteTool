<?php
// word_api.php - 单词本API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once dirname(__FILE__) . '/config.php';

$db = new Database();
$action = isset($_GET['action']) ? $_GET['action'] : '';

function sendJson($code, $message, $data = null) {
    $result = array('code' => $code, 'message' => $message);
    if ($data !== null) {
        $result['data'] = $data;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查登录状态
$isLoggedIn = isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user']['id'] : null;

// ============================================================
//  获取单词列表
// ============================================================
if ($action == 'list') {
    if (!$isLoggedIn) {
        sendJson(401, '请先登录');
    }
    $words = $db->getUserWords($userId);
    if ($words === false) {
        sendJson(500, '获取失败，请检查数据库连接');
    }
    sendJson(200, 'success', $words);
}

// ============================================================
//  添加单词
// ============================================================
else if ($action == 'add') {
    if (!$isLoggedIn) {
        sendJson(401, '请先登录');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $word = isset($input['word']) ? trim($input['word']) : '';
    $definition = isset($input['definition']) ? $input['definition'] : '';
    
    if (empty($word)) {
        sendJson(400, '单词不能为空');
    }
    
    $result = $db->addWord($userId, $word, $definition);
    if ($result === 'limit') {
        sendJson(429, '单词本已满（上限30个），请删除一些单词后再添加');
    } else if ($result === 'duplicate') {
        sendJson(400, '该单词已在您的单词本中');
    } else if ($result === true) {
        sendJson(200, '添加成功');
    } else {
        sendJson(500, '添加失败，请稍后重试');
    }
}

// ============================================================
//  删除单词
// ============================================================
else if ($action == 'delete') {
    if (!$isLoggedIn) {
        sendJson(401, '请先登录');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $word = isset($input['word']) ? trim($input['word']) : '';
    
    if (empty($word)) {
        sendJson(400, '单词不能为空');
    }
    
    $result = $db->deleteWord($userId, $word);
    if ($result) {
        sendJson(200, '删除成功');
    } else {
        sendJson(500, '删除失败，请稍后重试');
    }
}

// ============================================================
//  清空单词本
// ============================================================
else if ($action == 'clear') {
    if (!$isLoggedIn) {
        sendJson(401, '请先登录');
    }
    $result = $db->clearWords($userId);
    if ($result) {
        sendJson(200, '清空成功');
    } else {
        sendJson(500, '清空失败，请稍后重试');
    }
}

// ============================================================
//  同步本地单词到云端（批量添加）
// ============================================================
else if ($action == 'sync') {
    if (!$isLoggedIn) {
        sendJson(401, '请先登录');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $words = isset($input['words']) ? $input['words'] : array();
    
    if (!is_array($words) || empty($words)) {
        sendJson(400, '没有可同步的单词');
    }
    
    $added = 0;
    $skipped = 0;
    $limitReached = false;
    
    // 先获取当前已有单词
    $existing = $db->getUserWords($userId);
    if ($existing === false) {
        sendJson(500, '同步失败，请检查数据库连接');
    }
    $existingSet = array_flip($existing);
    
    foreach ($words as $word) {
        $word = trim($word);
        if (empty($word)) continue;
        if (isset($existingSet[$word])) {
            $skipped++;
            continue;
        }
        $result = $db->addWord($userId, $word, '');
        if ($result === 'limit') {
            $limitReached = true;
            break;
        } else if ($result === true) {
            $added++;
            $existingSet[$word] = true;
        }
    }
    
    $message = "同步完成：新增 {$added} 个";
    if ($skipped > 0) {
        $message .= "，跳过 {$skipped} 个（已存在）";
    }
    if ($limitReached) {
        $message .= "，已达到30个上限";
    }
    
    sendJson(200, $message, array(
        'added' => $added,
        'skipped' => $skipped,
        'limit_reached' => $limitReached,
        'total' => $db->getUserWords($userId)
    ));
}

else {
    sendJson(404, '未知请求');
}
?>