<?php
// oauth.php - 7e 统一登录（兼容错误URL格式）

// ============================================================
//  配置 - 请修改为您的实际值
// ============================================================
define('CLIENT_ID', 'client_ngIngIFp');
define('CLIENT_SECRET', 'secret_uTLFdfkyTlL3zePv9Feb3s3C');
define('REDIRECT_URI', 'http://lycbook.xyz/oauth.php?action=callback');
define('AUTH_URL', 'https://auth.7e.ink/oauth/authorize');
define('TOKEN_URL', 'https://api-auth.7e.ink/oauth/token');
define('USERINFO_URL', 'https://api-auth.7e.ink/oauth/userinfo');

session_start();

require_once dirname(__FILE__) . '/config.php';

// ============================================================
//  修复错误URL格式：?action=callback?code=xxx → 正确处理
// ============================================================

// 获取原始请求URI
$requestUri = $_SERVER['REQUEST_URI'];

// 如果URL中包含 ?action=callback? 这种错误格式，手动解析
if (strpos($requestUri, '?action=callback?') !== false) {
    // 提取 code 和 state
    preg_match('/code=([^&]+)/', $requestUri, $codeMatch);
    preg_match('/state=([^&]+)/', $requestUri, $stateMatch);
    
    if (!empty($codeMatch) && !empty($stateMatch)) {
        // 重新设置 $_GET
        $_GET['action'] = 'callback';
        $_GET['code'] = $codeMatch[1];
        $_GET['state'] = $stateMatch[1];
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 如果没有 action 但有 code，说明是回调
if (empty($action) && isset($_GET['code'])) {
    $action = 'callback';
}

// ============================================================
//  登录入口 - 跳转到7e授权页
// ============================================================
if ($action == 'login') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    
    $params = array(
        'client_id' => CLIENT_ID,
        'redirect_uri' => REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'profile',
        'state' => $state
    );
    
    $url = AUTH_URL . '?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

// ============================================================
//  回调处理
// ============================================================
if ($action == 'callback') {
    $code = isset($_GET['code']) ? $_GET['code'] : '';
    $state = isset($_GET['state']) ? $_GET['state'] : '';
    
    // 验证 state
    if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
        die('Invalid state');
    }
    unset($_SESSION['oauth_state']);
    
    if (empty($code)) {
        die('No code provided');
    }
    
    // 换取 Token
    $tokenData = exchangeToken($code);
    if (!$tokenData) {
        die('Failed to get token');
    }
    
    $accessToken = $tokenData['access_token'];
    
    // 获取用户信息
    $userInfo = getUserInfo($accessToken);
    if (!$userInfo) {
        die('Failed to get user info');
    }
    
    // 保存用户信息到 session
    $displayName = isset($userInfo['display_name']) ? $userInfo['display_name'] : $userInfo['username'];
    $email = isset($userInfo['email']) ? $userInfo['email'] : '';
    $avatarUrl = isset($userInfo['avatar_url']) ? $userInfo['avatar_url'] : '';
    
    $_SESSION['user'] = array(
        'id' => $userInfo['user_id'],
        'username' => $userInfo['username'],
        'display_name' => $displayName,
        'avatar_url' => $avatarUrl,
        'email' => $email,
        'access_token' => $accessToken,
        'logged_in' => true
    );
    
    // 跳转回主页
    header('Location: index.html');
    exit;
}

// ============================================================
//  退出登录
// ============================================================
if ($action == 'logout') {
    session_destroy();
    header('Location: index.html');
    exit;
}

// ============================================================
//  获取当前用户信息 (API)
// ============================================================
if ($action == 'me') {
    header('Content-Type: application/json');
    if (isset($_SESSION['user']) && $_SESSION['user']['logged_in']) {
        echo json_encode(array(
            'code' => 200,
            'data' => array(
                'id' => $_SESSION['user']['id'],
                'display_name' => $_SESSION['user']['display_name'],
                'avatar_url' => $_SESSION['user']['avatar_url']
            )
        ));
    } else {
        echo json_encode(array('code' => 401, 'message' => '未登录'));
    }
    exit;
}

// ============================================================
//  辅助函数
// ============================================================

function exchangeToken($code) {
    $params = array(
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => REDIRECT_URI
    );
    
    $ch = curl_init(TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return false;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        return false;
    }
    
    return $data;
}

function getUserInfo($accessToken) {
    $ch = curl_init(USERINFO_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $accessToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return false;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['user_id'])) {
        return false;
    }
    
    return $data;
}
?>