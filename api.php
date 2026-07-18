<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__FILE__) . '/config.php';

$config = new ConfigManager();
$action = isset($_GET['action']) ? $_GET['action'] : '';

function sendResponse($code, $message, $data = null) {
    $response = array('code' => $code, 'message' => $message);
    if (!is_null($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

switch ($action) {
    case 'get_settings':
        $settings = $config->getAll();
        sendResponse(200, 'success', array(
            'theme_color' => $settings['theme_color'],
            'site_title' => $settings['site_title'],
            'welcome_modal' => $settings['welcome_modal']
        ));
        break;
    
    default:
        sendResponse(404, '未知的API请求');
        break;
}
?>