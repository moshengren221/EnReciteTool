<?php
// dictionary_api.php - 词库管理API（修复SQL语法错误）
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once dirname(__FILE__) . '/config.php';

// 检查登录状态
$isLoggedIn = false;
$userId = null;
if (isset($_SESSION['user']) && isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in'] === true) {
    $isLoggedIn = true;
    $userId = $_SESSION['user']['id'];
}

$isAdmin = $isLoggedIn;

function sendJson($code, $message, $data = null) {
    $result = array('code' => $code, 'message' => $message);
    if ($data !== null) {
        $result['data'] = $data;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
//  数据库操作类
// ============================================================
class DictionaryDB {
    private $host = 'localhost';
    private $dbname = '你的数据库名';
    private $username = '你的数据库账号';
    private $password = '你的密码';
    private $conn = null;
    private $lastError = '';

    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->conn = null;
        }
    }

    public function isConnected() {
        return $this->conn !== null;
    }

    public function getLastError() {
        return $this->lastError;
    }

    // 获取所有词条（分页）- 使用 intval 确保参数是整数
    public function getWords($page = 1, $limit = 50, $search = '') {
        if (!$this->isConnected()) return false;
        try {
            $page = intval($page);
            $limit = intval($limit);
            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 50;
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT id, word, definition, created_at FROM dictionary";
            $params = array();
            
            if (!empty($search)) {
                $sql .= " WHERE word LIKE ? OR definition LIKE ?";
                $searchParam = '%' . $search . '%';
                $params = array($searchParam, $searchParam);
            }
            
            $sql .= " ORDER BY word ASC LIMIT " . $limit . " OFFSET " . $offset;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // 获取总数
    public function getTotalCount($search = '') {
        if (!$this->isConnected()) return 0;
        try {
            $sql = "SELECT COUNT(*) FROM dictionary";
            $params = array();
            if (!empty($search)) {
                $sql .= " WHERE word LIKE ? OR definition LIKE ?";
                $searchParam = '%' . $search . '%';
                $params = array($searchParam, $searchParam);
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    // 添加词条
    public function addWord($word, $definition) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("INSERT INTO dictionary (word, definition) VALUES (?, ?)");
            $stmt->execute(array($word, $definition));
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return 'duplicate';
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // 批量添加词条
    public function addWords($words) {
        if (!$this->isConnected()) return false;
        try {
            $added = 0;
            $skipped = 0;
            $this->conn->beginTransaction();
            foreach ($words as $item) {
                try {
                    $stmt = $this->conn->prepare("INSERT INTO dictionary (word, definition) VALUES (?, ?)");
                    $stmt->execute(array($item['word'], $item['definition']));
                    $added++;
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $skipped++;
                    } else {
                        throw $e;
                    }
                }
            }
            $this->conn->commit();
            return array('added' => $added, 'skipped' => $skipped);
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // 更新词条
    public function updateWord($id, $word, $definition) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("UPDATE dictionary SET word = ?, definition = ? WHERE id = ?");
            $stmt->execute(array($word, $definition, $id));
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return 'duplicate';
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // 删除词条
    public function deleteWord($id) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("DELETE FROM dictionary WHERE id = ?");
            $stmt->execute(array($id));
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // 清空所有词条
    public function clearAll() {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("TRUNCATE TABLE dictionary");
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // 获取单个词条
    public function getWord($id) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("SELECT id, word, definition FROM dictionary WHERE id = ?");
            $stmt->execute(array($id));
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}

$db = new DictionaryDB();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================================
//  获取词条列表
// ============================================================
if ($action == 'list') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 50;
    if ($limit > 500) $limit = 500;  // 防止过大
    
    $words = $db->getWords($page, $limit, $search);
    if ($words === false) {
        sendJson(500, '获取失败：' . $db->getLastError());
    }
    $total = $db->getTotalCount($search);
    sendJson(200, 'success', array(
        'words' => $words,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($total / $limit)
    ));
}

// ============================================================
//  添加单个词条
// ============================================================
else if ($action == 'add') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $word = isset($input['word']) ? trim($input['word']) : '';
    $definition = isset($input['definition']) ? trim($input['definition']) : '';
    
    if (empty($word) || empty($definition)) {
        sendJson(400, '单词和释义都不能为空');
    }
    
    $result = $db->addWord($word, $definition);
    if ($result === true) {
        sendJson(200, '添加成功');
    } else if ($result === 'duplicate') {
        sendJson(400, '该单词已存在于词库中');
    } else {
        sendJson(500, '添加失败：' . $db->getLastError());
    }
}

// ============================================================
//  批量添加（上传文件）
// ============================================================
else if ($action == 'upload') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
        sendJson(400, '请选择有效的文件');
    }
    
    $content = file_get_contents($_FILES['file']['tmp_name']);
    $lines = explode("\n", $content);
    $words = array();
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || substr($line, 0, 1) == '#') continue;
        
        $word = '';
        $definition = '';
        
        if (strpos($line, "\t") !== false) {
            $parts = explode("\t", $line);
            $word = trim($parts[0]);
            $definition = trim($parts[1] ?? '');
        } else {
            $match = preg_match('/^([a-zA-Z\'\-]+)\s*[:：,，\s]\s*(.+)$/', $line, $matches);
            if ($match) {
                $word = trim($matches[1]);
                $definition = trim($matches[2]);
            } else {
                continue;
            }
        }
        
        if (!empty($word) && !empty($definition)) {
            $words[] = array('word' => $word, 'definition' => $definition);
        }
    }
    
    if (empty($words)) {
        sendJson(400, '未识别到有效的词条数据');
    }
    
    $result = $db->addWords($words);
    if ($result === false) {
        sendJson(500, '导入失败：' . $db->getLastError());
    }
    
    sendJson(200, "导入完成：新增 {$result['added']} 个，跳过 {$result['skipped']} 个（已存在）", $result);
}

// ============================================================
//  更新词条
// ============================================================
else if ($action == 'update') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $word = isset($input['word']) ? trim($input['word']) : '';
    $definition = isset($input['definition']) ? trim($input['definition']) : '';
    
    if ($id <= 0 || empty($word) || empty($definition)) {
        sendJson(400, '参数不完整');
    }
    
    $result = $db->updateWord($id, $word, $definition);
    if ($result === true) {
        sendJson(200, '更新成功');
    } else if ($result === 'duplicate') {
        sendJson(400, '该单词已存在于词库中');
    } else {
        sendJson(500, '更新失败：' . $db->getLastError());
    }
}

// ============================================================
//  删除词条
// ============================================================
else if ($action == 'delete') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : 0;
    
    if ($id <= 0) {
        sendJson(400, '无效的ID');
    }
    
    $result = $db->deleteWord($id);
    if ($result) {
        sendJson(200, '删除成功');
    } else {
        sendJson(500, '删除失败：' . $db->getLastError());
    }
}

// ============================================================
//  清空词库
// ============================================================
else if ($action == 'clear') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    $result = $db->clearAll();
    if ($result) {
        sendJson(200, '词库已清空');
    } else {
        sendJson(500, '清空失败：' . $db->getLastError());
    }
}

// ============================================================
//  获取单个词条
// ============================================================
else if ($action == 'get') {
    if (!$isAdmin) {
        sendJson(401, '请先登录');
    }
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        sendJson(400, '无效的ID');
    }
    $word = $db->getWord($id);
    if ($word === false) {
        sendJson(500, '获取失败：' . $db->getLastError());
    }
    if (!$word) {
        sendJson(404, '词条不存在');
    }
    sendJson(200, 'success', $word);
}

else {
    sendJson(404, '未知请求');
}
?>
