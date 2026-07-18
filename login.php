<?php
session_start();

define('ADMIN_PASSWORD', 'admin123');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('code' => 405, 'message' => '方法不允许'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$password = isset($input['password']) ? $input['password'] : '';

if ($password === ADMIN_PASSWORD) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['login_time'] = time();
    echo json_encode(array('code' => 200, 'message' => '登录成功'));
} else {
    echo json_encode(array('code' => 401, 'message' => '密码错误'));
}
?>