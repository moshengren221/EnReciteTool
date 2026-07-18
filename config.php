<?php
// config.php
class ConfigManager {
    private $configFile;
    private $defaults;

    public function __construct() {
        $this->configFile = __DIR__ . '/data/settings.json';
        $this->defaults = array(
            'theme_color' => '#0969da',
            'site_title' => '英语文章背诵助手',
            'welcome_modal' => array(
                'enabled' => true,
                'title' => '📚 欢迎使用背诵助手',
                'content' => '欢迎来到英语文章背诵助手！祝您学习愉快！'
            )
        );
        
        $dataDir = dirname($this->configFile);
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        if (!file_exists($this->configFile)) {
            file_put_contents($this->configFile, json_encode($this->defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function getAll() {
        if (!file_exists($this->configFile)) {
            return $this->defaults;
        }
        $content = file_get_contents($this->configFile);
        $data = json_decode($content, true);
        if (!$data) {
            return $this->defaults;
        }
        return array_merge($this->defaults, $data);
    }

    public function get($key, $default = null) {
        $data = $this->getAll();
        $keys = explode('.', $key);
        $value = $data;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function set($key, $value) {
        $data = $this->getAll();
        $keys = explode('.', $key);
        $ref = &$data;
        foreach ($keys as $k) {
            if (!isset($ref[$k])) {
                $ref[$k] = array();
            }
            $ref = &$ref[$k];
        }
        $ref = $value;
        $result = file_put_contents($this->configFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $result !== false;
    }

    public function setMultiple($updates) {
        $data = $this->getAll();
        foreach ($updates as $key => $value) {
            $keys = explode('.', $key);
            $ref = &$data;
            foreach ($keys as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array();
                }
                $ref = &$ref[$k];
            }
            $ref = $value;
        }
        $result = file_put_contents($this->configFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $result !== false;
    }

    public function reset() {
        $result = file_put_contents($this->configFile, json_encode($this->defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $result !== false;
    }
}

// ============================================================
//  数据库操作类
// ============================================================
class Database {
    private $host = 'localhost';
    private $dbname = 'sfydb_6309084';
    private $username = 'sfydb_6309084';
    private $password = 'Lyc0927.';
    private $conn = null;

    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // 静默失败，使用本地存储
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function isConnected() {
        return $this->conn !== null;
    }

    // 获取用户的单词列表
    public function getUserWords($userId) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("SELECT word, definition FROM user_words WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute(array($userId));
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $words = array();
            foreach ($result as $row) {
                $words[] = $row['word'];
            }
            return $words;
        } catch (PDOException $e) {
            return false;
        }
    }

    // 添加单词
    public function addWord($userId, $word, $definition = '') {
        if (!$this->isConnected()) return false;
        try {
            // 先检查数量
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM user_words WHERE user_id = ?");
            $stmt->execute(array($userId));
            $count = $stmt->fetchColumn();
            if ($count >= 30) {
                return 'limit';
            }
            $stmt = $this->conn->prepare("INSERT INTO user_words (user_id, word, definition) VALUES (?, ?, ?)");
            $stmt->execute(array($userId, $word, $definition));
            return true;
        } catch (PDOException $e) {
            // 重复词
            if ($e->getCode() == 23000) {
                return 'duplicate';
            }
            return false;
        }
    }

    // 删除单词
    public function deleteWord($userId, $word) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("DELETE FROM user_words WHERE user_id = ? AND word = ?");
            $stmt->execute(array($userId, $word));
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // 清空所有单词
    public function clearWords($userId) {
        if (!$this->isConnected()) return false;
        try {
            $stmt = $this->conn->prepare("DELETE FROM user_words WHERE user_id = ?");
            $stmt->execute(array($userId));
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>