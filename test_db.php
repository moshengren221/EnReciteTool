<?php
// test_db.php - 测试数据库连接
header('Content-Type: text/html; charset=utf-8');

echo "<h2>数据库连接测试</h2>";

// 您的数据库配置
$host = 'localhost';
$dbname = 'sfydb_6309084';
$username = 'sfydb_6309084';
$password = 'your_password';  // 请替换为您的真实密码

echo "<p><strong>配置信息：</strong></p>";
echo "<pre>";
echo "Host: $host\n";
echo "Database: $dbname\n";
echo "Username: $username\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n";
echo "</pre>";

try {
    $conn = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8",
        $username,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✅ 数据库连接成功！</p>";
    
    // 检查表是否存在
    $stmt = $conn->query("SHOW TABLES LIKE 'user_words'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✅ 表 `user_words` 存在</p>";
        
        // 查看表结构
        $stmt = $conn->query("DESCRIBE user_words");
        echo "<p><strong>表结构：</strong></p>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>字段</th><th>类型</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ 表 `user_words` 不存在！请先创建表。</p>";
        echo "<p>执行以下SQL创建表：</p>";
        echo "<pre style='background:#f0f0f0;padding:10px;'>";
        echo "CREATE TABLE IF NOT EXISTS `user_words` (\n";
        echo "    `id` int(11) NOT NULL AUTO_INCREMENT,\n";
        echo "    `user_id` int(11) NOT NULL,\n";
        echo "    `word` varchar(100) NOT NULL,\n";
        echo "    `definition` text,\n";
        echo "    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
        echo "    PRIMARY KEY (`id`),\n";
        echo "    UNIQUE KEY `user_word` (`user_id`, `word`),\n";
        echo "    KEY `user_id` (`user_id`)\n";
        echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ 数据库连接失败！</p>";
    echo "<p><strong>错误信息：</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>可能原因：</strong></p>";
    echo "<ul>";
    echo "<li>数据库用户名或密码错误</li>";
    echo "<li>数据库不存在</li>";
    echo "<li>数据库服务器未启动</li>";
    echo "<li>防火墙阻止了连接</li>";
    echo "</ul>";
}
?>