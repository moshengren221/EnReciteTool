<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__FILE__) . '/config.php';

session_start();

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '{"code":401,"message":"请先登录"}';
    exit;
}

$config = new ConfigManager();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 响应函数
function sendJson($code, $message, $data = null) {
    $result = array('code' => $code, 'message' => $message);
    if ($data !== null) {
        $result['data'] = $data;
    }
    echo json_encode($result);
    exit;
}

// 处理请求
if ($action == 'get_settings') {
    sendJson(200, 'success', $config->getAll());
}
else if ($action == 'update_setting') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['key']) || !isset($input['value'])) {
        sendJson(400, '缺少参数');
    }
    $config->set($input['key'], $input['value']);
    sendJson(200, '更新成功');
}
else if ($action == 'update_settings') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['settings'])) {
        sendJson(400, '缺少参数');
    }
    $config->setMultiple($input['settings']);
    sendJson(200, '更新成功');
}
else if ($action == 'update_welcome_modal') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        sendJson(400, '无效数据');
    }
    $updates = array();
    if (isset($input['enabled'])) {
        $updates['welcome_modal.enabled'] = $input['enabled'] ? true : false;
    }
    if (isset($input['title'])) {
        $updates['welcome_modal.title'] = $input['title'];
    }
    if (isset($input['content'])) {
        $updates['welcome_modal.content'] = $input['content'];
    }
    $config->setMultiple($updates);
    sendJson(200, '更新成功', $config->get('welcome_modal'));
}
else if ($action == 'reset_settings') {
    $config->reset();
    sendJson(200, '已重置', $config->getAll());
}
else {
    sendJson(404, '未知请求');
}
?>