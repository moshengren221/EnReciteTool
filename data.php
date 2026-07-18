<?php
session_start();
$isLoggedIn = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isLoggedIn = true;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 英语背诵助手</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; background: #f6f8fa; }
        
        /* 头部 */
        .header {
            background: #24292f;
            color: #fff;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { margin: 0; font-size: 18px; }
        .header a { color: #8b949e; text-decoration: none; }
        .header a:hover { color: #fff; }
        
        /* 登录框 */
        .login-box {
            max-width: 400px;
            margin: 80px auto;
            padding: 40px;
            background: #fff;
            border: 1px solid #d0d7de;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .login-box h2 { margin-top: 0; text-align: center; }
        .login-box input[type="password"] {
            width: 100%;
            padding: 10px 14px;
            font-size: 16px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .login-box input[type="password"]:focus {
            border-color: #0969da;
            outline: none;
            box-shadow: 0 0 0 3px rgba(9,105,218,0.3);
        }
        
        /* 主面板 */
        .panel {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .box {
            background: #fff;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .box-header {
            background: #f6f8fa;
            border-bottom: 1px solid #d0d7de;
            padding: 16px;
        }
        .box-title { font-size: 14px; font-weight: 600; margin: 0; }
        .box-body { padding: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input[type="color"] {
            width: 60px;
            height: 40px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 2px;
            cursor: pointer;
        }
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 150px;
        }
        
        /* 错误/成功消息 */
        .error-msg {
            color: #cf222e;
            background: #ffebe9;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            display: none;
        }
        .success-msg {
            color: #1a7f37;
            background: #dafbe1;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            display: none;
        }
        
        /* 开关行 */
        .switch-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .switch-row input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        /* 按钮 */
        .btn {
            font-size: 14px;
            font-weight: 500;
            padding: 5px 16px;
            border-radius: 6px;
            border: 1px solid rgba(27,31,36,0.15);
            background: #f6f8fa;
            color: #24292f;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
        }
        .btn:hover { background: #f3f4f6; }
        .btn-primary {
            background: #2da44e;
            color: #fff;
            border-color: rgba(27,31,36,0.15);
        }
        .btn-primary:hover { background: #2c974b; }
        .btn-danger { color: #cf222e; }
        .btn-danger:hover {
            background: #ffebe9;
            border-color: rgba(207,34,46,0.15);
        }
        .mt-8 { margin-top: 8px; }
        .text-muted { color: #57606a; font-size: 13px; }
        .gap-12 { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        
        /* 弹窗 */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background: #fff;
            border-radius: 12px;
            max-width: 480px;
            width: 90%;
            padding: 24px 28px 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            border: 1px solid #d0d7de;
        }
        .modal-box h2 { margin-top: 0; font-size: 20px; }
        .modal-box p { color: #57606a; line-height: 1.6; }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        /* 前端同步状态指示器 */
        .sync-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #57606a;
            background: #f6f8fa;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid #d0d7de;
        }
        .sync-status .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2da44e;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .help-text {
            font-size: 13px;
            color: #57606a;
            margin-top: 6px;
            padding: 8px 12px;
            background: #f6f8fa;
            border-radius: 6px;
            border-left: 3px solid #0969da;
        }
        .help-text strong { color: #24292f; }
        
        /* 颜色预览块 */
        .color-preview {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid #d0d7de;
            vertical-align: middle;
            margin-left: 8px;
        }
        .form-group .color-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group .color-row span {
            font-size: 13px;
            color: #57606a;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 后台管理</h1>
        <?php if ($isLoggedIn): ?>
        <a href="admin.php?logout=1" onclick="return confirm('确认退出登录？')">退出登录</a>
        <?php endif; ?>
    </div>

    <?php if (!$isLoggedIn): ?>
    <!-- ==================== 登录界面 ==================== -->
    <div class="login-box">
        <h2>🔐 管理员登录</h2>
        <div id="loginError" class="error-msg">密码错误，请重试</div>
        <form id="loginForm" onsubmit="return handleLogin(event)">
            <input type="password" id="adminPassword" placeholder="请输入管理密码" required autofocus>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">登录</button>
        </form>
        <p style="text-align:center; color:#57606a; font-size:13px; margin-top:12px;">默认密码：admin123</p>
    </div>
    <script>
        function handleLogin(e) {
            e.preventDefault();
            var password = document.getElementById('adminPassword').value;
            var errorEl = document.getElementById('loginError');
            
            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: password })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.code === 200) {
                    location.reload();
                } else {
                    errorEl.style.display = 'block';
                    errorEl.textContent = data.message || '密码错误';
                    setTimeout(function() { errorEl.style.display = 'none'; }, 3000);
                }
            })
            .catch(function(err) {
                errorEl.style.display = 'block';
                errorEl.textContent = '网络错误，请检查服务器';
                setTimeout(function() { errorEl.style.display = 'none'; }, 3000);
            });
            return false;
        }
    </script>
    
    <?php else: ?>
    <!-- ==================== 管理面板 ==================== -->
    <div class="panel">
        
        <!-- 同步状态提示 -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div class="sync-status">
                <span class="dot"></span>
                <span>前端自动同步已开启（每5秒检查一次）</span>
            </div>
            <span style="font-size:13px; color:#57606a;">
                修改后无需任何操作，前端将自动更新
            </span>
        </div>
        
        <!-- ===== 外观设置 ===== -->
        <div class="box">
            <div class="box-header"><h3 class="box-title">🎨 外观设置</h3></div>
            <div class="box-body">
                <div id="appearanceSuccess" class="success-msg">✅ 保存成功！前端将自动同步</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>主题色（按钮/链接/强调色）</label>
                        <div class="color-row">
                            <input type="color" id="themeColor" value="#0969da">
                            <span id="themeColorLabel">#0969da</span>
                            <span class="color-preview" id="themeColorPreview" style="background:#0969da;"></span>
                        </div>
                        <div style="font-size:12px; color:#8b949e; margin-top:4px;">
                            控制按钮、链接、选中高亮等颜色
                        </div>
                    </div>
                    <div class="form-group">
                        <label>网站标题</label>
                        <input type="text" id="siteTitle" placeholder="网站标题">
                    </div>
                </div>
                <div class="gap-12">
                    <button class="btn btn-primary" onclick="saveAppearance()">💾 保存外观设置</button>
                    <span style="font-size:13px; color:#57606a;">⚡ 前端将自动同步</span>
                </div>
                <div class="help-text">
                    💡 <strong>提示：</strong>修改主题色后，前端页面将在 <strong>5秒内</strong> 自动更新按钮和链接的颜色。
                </div>
            </div>
        </div>
        
        <!-- ===== 欢迎弹窗设置 ===== -->
        <div class="box">
            <div class="box-header"><h3 class="box-title">📢 欢迎弹窗设置</h3></div>
            <div class="box-body">
                <div id="modalSuccess" class="success-msg">✅ 保存成功！前端将自动同步</div>
                <div class="form-group switch-row">
                    <input type="checkbox" id="modalEnabled" checked>
                    <label for="modalEnabled">启用欢迎弹窗</label>
                </div>
                <div class="form-group">
                    <label>弹窗标题</label>
                    <input type="text" id="modalTitle" placeholder="弹窗标题">
                </div>
                <div class="form-group">
                    <label>弹窗内容</label>
                    <textarea id="modalContent" rows="4" placeholder="弹窗内容"></textarea>
                </div>
                <div class="gap-12">
                    <button class="btn btn-primary" onclick="saveModal()">💾 保存弹窗设置</button>
                    <button class="btn" onclick="previewModal()">👁️ 预览弹窗</button>
                    <span style="font-size:13px; color:#57606a;">⚡ 前端将自动同步</span>
                </div>
                <div class="help-text">
                    💡 <strong>提示：</strong>保存后，前端页面将在 <strong>5秒内</strong> 自动更新欢迎弹窗的内容。<br>
                    <span style="font-size:12px; color:#8b949e;">* 弹窗遮罩固定为高斯模糊效果，不可修改</span>
                </div>
            </div>
        </div>
        
        <!-- ===== 其他操作 ===== -->
        <div class="box">
            <div class="box-header"><h3 class="box-title">⚙️ 其他操作</h3></div>
            <div class="box-body">
                <div class="gap-12">
                    <button class="btn btn-danger" onclick="resetAll()">🔄 重置所有设置为默认值</button>
                </div>
                <p class="text-muted mt-8">重置后将恢复所有配置到初始状态，前端将自动同步。</p>
                <div class="help-text" style="border-left-color:#cf222e;">
                    ⚠️ <strong>注意：</strong>重置操作不可撤销，请谨慎使用。
                </div>
            </div>
        </div>
        
        <!-- ===== 当前配置预览 ===== -->
        <div class="box">
            <div class="box-header"><h3 class="box-title">📋 当前配置</h3></div>
            <div class="box-body">
                <pre id="configPreview" style="background:#f6f8fa; padding:12px; border-radius:6px; font-size:12px; overflow:auto; max-height:200px; margin:0; border:1px solid #d0d7de;"></pre>
                <button class="btn btn-sm" onclick="refreshConfigPreview()" style="margin-top:8px;">🔄 刷新预览</button>
            </div>
        </div>
    </div>
    
    <!-- ===== 弹窗 ===== -->
    <div class="modal-overlay" id="adminModal">
        <div class="modal-box">
            <h2 id="adminModalTitle">提示</h2>
            <p id="adminModalBody">内容</p>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="closeAdminModal()">确定</button>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================================
        //  颜色选择器实时预览
        // ============================================================
        document.getElementById('themeColor').addEventListener('input', function() {
            var val = this.value;
            document.getElementById('themeColorLabel').textContent = val;
            document.getElementById('themeColorPreview').style.background = val;
        });
        
        // ============================================================
        //  工具函数
        // ============================================================
        function showSuccess(el) {
            el.style.display = 'block';
            setTimeout(function() { el.style.display = 'none'; }, 4000);
        }
        
        function showModal(title, body) {
            document.getElementById('adminModalTitle').innerText = title;
            document.getElementById('adminModalBody').innerText = body;
            document.getElementById('adminModal').classList.add('active');
        }
        
        function closeAdminModal() {
            document.getElementById('adminModal').classList.remove('active');
        }
        document.getElementById('adminModal').addEventListener('click', function(e) {
            if (e.target === this) closeAdminModal();
        });
        
        // ============================================================
        //  加载配置
        // ============================================================
        function loadSettings() {
            fetch('admin_api.php?action=get_settings')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.code === 200) {
                    var s = data.data;
                    document.getElementById('themeColor').value = s.theme_color || '#0969da';
                    document.getElementById('themeColorLabel').textContent = s.theme_color || '#0969da';
                    document.getElementById('themeColorPreview').style.background = s.theme_color || '#0969da';
                    document.getElementById('siteTitle').value = s.site_title || '';
                    document.getElementById('modalEnabled').checked = s.welcome_modal && s.welcome_modal.enabled !== false;
                    document.getElementById('modalTitle').value = s.welcome_modal && s.welcome_modal.title || '';
                    document.getElementById('modalContent').value = s.welcome_modal && s.welcome_modal.content || '';
                    document.getElementById('configPreview').textContent = JSON.stringify(s, null, 2);
                }
            })
            .catch(function(e) {
                showModal('⚠️ 错误', '加载配置失败，请检查服务器');
            });
        }
        loadSettings();
        
        function refreshConfigPreview() {
            loadSettings();
            showModal('🔄 已刷新', '配置预览已更新');
        }
        
        // ============================================================
        //  保存外观（只保存主题色和标题，不保存遮罩）
        // ============================================================
        function saveAppearance() {
            var settings = {
                'theme_color': document.getElementById('themeColor').value,
                'site_title': document.getElementById('siteTitle').value || '英语文章背诵助手'
            };
            fetch('admin_api.php?action=update_settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ settings: settings })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.code === 200) {
                    showSuccess(document.getElementById('appearanceSuccess'));
                    loadSettings();
                } else {
                    showModal('❌ 保存失败', data.message || '未知错误');
                }
            })
            .catch(function(e) {
                showModal('❌ 网络错误', '请检查服务器连接');
            });
        }
        
        // ============================================================
        //  保存弹窗
        // ============================================================
        function saveModal() {
            var data = {
                enabled: document.getElementById('modalEnabled').checked,
                title: document.getElementById('modalTitle').value || '📚 欢迎',
                content: document.getElementById('modalContent').value || '欢迎使用！'
            };
            fetch('admin_api.php?action=update_welcome_modal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(function(res) { return res.json(); })
            .then(function(result) {
                if (result.code === 200) {
                    showSuccess(document.getElementById('modalSuccess'));
                    loadSettings();
                } else {
                    showModal('❌ 保存失败', result.message || '未知错误');
                }
            })
            .catch(function(e) {
                showModal('❌ 网络错误', '请检查服务器连接');
            });
        }
        
        // ============================================================
        //  预览弹窗
        // ============================================================
        function previewModal() {
            var title = document.getElementById('modalTitle').value || '📚 欢迎';
            var content = document.getElementById('modalContent').value || '预览内容';
            showModal(title, content);
        }
        
        // ============================================================
        //  重置
        // ============================================================
        function resetAll() {
            if (!confirm('确认重置所有设置为默认值吗？此操作不可撤销！')) return;
            fetch('admin_api.php?action=reset_settings', { method: 'POST' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.code === 200) {
                    showModal('✅ 重置成功', '所有配置已恢复为默认值！\n前端将在5秒内自动同步。');
                    loadSettings();
                } else {
                    showModal('❌ 重置失败', data.message || '未知错误');
                }
            })
            .catch(function(e) {
                showModal('❌ 网络错误', '请检查服务器连接');
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>