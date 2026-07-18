// script.js - 完整前端逻辑（含 7e 登录 + 云端单词本）

// ============================================================
//  状态
// ============================================================
var state = {
    dictionary: {},
    stars: new Set(),
    activeMode: 'study',
    fontSize: 18,
    hideDictWords: false,
    currentSelectedWord: null,
    isMobile: false,
    settings: {},
    welcomeModal: { enabled: true, title: '📚 欢迎', content: '欢迎使用！' },
    lastSettingsHash: '',
    cloudWords: [],
    wordLimit: 30
};

var audioCtx = null;
var syncTimer = null;

// ============================================================
//  用户登录状态管理
// ============================================================
var user = {
    loggedIn: false,
    id: null,
    displayName: '',
    avatarUrl: '',
    words: [],
    wordLimit: 30
};

// 检查登录状态
function checkLoginStatus() {
    fetch('oauth.php?action=me')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 200) {
                user.loggedIn = true;
                user.id = data.data.id;
                user.displayName = data.data.display_name;
                user.avatarUrl = data.data.avatar_url;
                showUserInfo();
                loadCloudWords();
            } else {
                user.loggedIn = false;
                showLoginButton();
            }
        })
        .catch(function(e) {
            console.warn('检查登录状态失败', e);
            showLoginButton();
        });
}

// 显示用户信息
function showUserInfo() {
    var userArea = document.getElementById('userArea');
    var loginBtn = document.getElementById('loginBtn');
    var syncBtn = document.getElementById('syncCloudBtn');
    if (userArea) userArea.style.display = 'flex';
    if (loginBtn) loginBtn.style.display = 'none';
    var avatar = document.getElementById('userAvatar');
    if (avatar) avatar.src = user.avatarUrl || 'https://dicebear.7e.ink/7.x/avataaars/svg?seed=default';
    var name = document.getElementById('userName');
    if (name) name.textContent = user.displayName || '用户';
    if (syncBtn) syncBtn.style.display = 'inline-flex';
    updateWordCountBadge();
}

// 显示登录按钮
function showLoginButton() {
    var userArea = document.getElementById('userArea');
    var loginBtn = document.getElementById('loginBtn');
    var syncBtn = document.getElementById('syncCloudBtn');
    if (userArea) userArea.style.display = 'none';
    if (loginBtn) loginBtn.style.display = 'inline-flex';
    if (syncBtn) syncBtn.style.display = 'none';
}

// 登录
function login() {
    window.location.href = 'oauth.php?action=login';
}

// 退出
function logout() {
    if (confirm('确认退出登录吗？退出后单词本将切换为本地存储。')) {
        window.location.href = 'oauth.php?action=logout';
    }
}

// 更新单词数量徽章
function updateWordCountBadge() {
    var badge = document.getElementById('wordCountBadge');
    if (badge) {
        var count = user.loggedIn ? (user.words ? user.words.length : 0) : state.stars.size;
        badge.textContent = count + '/' + user.wordLimit;
        if (count >= user.wordLimit) {
            badge.style.background = '#cf222e';
        } else {
            badge.style.background = '#2da44e';
        }
    }
}

// ============================================================
//  云端单词本操作
// ============================================================

// 加载云端单词
function loadCloudWords() {
    fetch('word_api.php?action=list')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 200) {
                user.words = data.data || [];
                updateWordCountBadge();
                mergeCloudWordsToState();
            }
        })
        .catch(function(e) {
            console.warn('加载云端单词失败', e);
        });
}

// 合并云端单词到本地状态
function mergeCloudWordsToState() {
    if (user.words && user.words.length > 0) {
        var added = 0;
        user.words.forEach(function(word) {
            if (!state.stars.has(word)) {
                state.stars.add(word);
                added++;
            }
        });
        if (added > 0) {
            saveStarsToCache();
            renderStarredList();
        }
    }
    updateWordCountBadge();
}

// 添加单词（支持云端和本地）
function addWordWithCloud(word) {
    var w = word.toLowerCase();
    
    if (user.loggedIn) {
        // 登录状态：先尝试添加到云端
        return fetch('word_api.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ word: w })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 200) {
                if (!state.stars.has(w)) {
                    state.stars.add(w);
                    playAddSound();
                    saveStarsToCache();
                }
                loadCloudWords();
                renderStarredList();
                return { success: true, message: '添加成功' };
            } else if (data.code === 429) {
                return { success: false, message: data.message };
            } else if (data.code === 400 && data.message.indexOf('已在您的单词本') !== -1) {
                return { success: false, message: '该单词已在单词本中' };
            } else {
                return { success: false, message: data.message || '添加失败' };
            }
        })
        .catch(function(e) {
            return { success: false, message: '网络错误，请重试' };
        });
    } else {
        // 未登录：存本地
        if (!state.stars.has(w)) {
            state.stars.add(w);
            playAddSound();
            saveStarsToCache();
            renderStarredList();
            updateWordCountBadge();
            return Promise.resolve({ success: true, message: '已添加到本地单词本' });
        } else {
            return Promise.resolve({ success: false, message: '该单词已在单词本中' });
        }
    }
}

// 删除单词（支持云端和本地）
function deleteWordWithCloud(word) {
    var w = word.toLowerCase();
    
    if (user.loggedIn) {
        return fetch('word_api.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ word: w })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 200) {
                if (state.stars.has(w)) {
                    state.stars.delete(w);
                    playDeleteSound();
                    saveStarsToCache();
                }
                loadCloudWords();
                renderStarredList();
                updateWordCountBadge();
                return { success: true };
            } else {
                return { success: false, message: data.message || '删除失败' };
            }
        })
        .catch(function(e) {
            return { success: false, message: '网络错误，请重试' };
        });
    } else {
        if (state.stars.has(w)) {
            state.stars.delete(w);
            playDeleteSound();
            saveStarsToCache();
            renderStarredList();
            updateWordCountBadge();
            return Promise.resolve({ success: true });
        } else {
            return Promise.resolve({ success: false, message: '单词不存在' });
        }
    }
}

// 清空单词本（云端）
function clearCloudWords() {
    if (!user.loggedIn) {
        // 未登录：清空本地
        if (confirm('确认要全部清空单词本吗？此操作不可撤销！')) {
            state.stars.clear();
            playDeleteSound();
            saveStarsToCache();
            renderStarredList();
            updateWordCountBadge();
            showCustomModal('✅ 清空成功', '本地单词本已全部清空。');
        }
        return;
    }
    
    // 登录状态：显示确认对话框
    if (confirm('确认要全部清空单词本吗？此操作不可撤销！')) {
        fetch('word_api.php?action=clear', {
            method: 'POST'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 200) {
                state.stars.clear();
                playDeleteSound();
                saveStarsToCache();
                loadCloudWords();
                renderStarredList();
                updateWordCountBadge();
                showCustomModal('✅ 清空成功', '您的单词本已全部清空。');
            } else {
                showCustomModal('❌ 清空失败', data.message || '请稍后重试');
            }
        })
        .catch(function(e) {
            showCustomModal('❌ 网络错误', '请检查网络连接');
        });
    }
}

// 同步本地单词到云端
function syncToCloud() {
    if (!user.loggedIn) {
        showCustomModal('提示', '请先登录后再同步');
        return;
    }
    
    var localWords = Array.from(state.stars);
    if (localWords.length === 0) {
        showCustomModal('提示', '本地单词本为空，无需同步');
        return;
    }
    
    // 检查是否超出限制
    var currentCloudCount = user.words ? user.words.length : 0;
    var available = user.wordLimit - currentCloudCount;
    if (available <= 0) {
        showCustomModal('⚠️ 提示', '您的云端单词本已满（上限30个），请先删除一些单词再同步。');
        return;
    }
    if (localWords.length > available) {
        var msg = '您有 ' + localWords.length + ' 个单词要同步，但云端剩余空间只有 ' + available + ' 个。\n\n将只同步前 ' + available + ' 个单词。';
        if (!confirm(msg)) return;
    }
    
    fetch('word_api.php?action=sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ words: localWords })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.code === 200) {
            loadCloudWords();
            renderStarredList();
            updateWordCountBadge();
            showCustomModal('✅ 同步完成', data.message);
        } else {
            showCustomModal('❌ 同步失败', data.message || '请稍后重试');
        }
    })
    .catch(function(e) {
        showCustomModal('❌ 网络错误', '请检查网络连接');
    });
}

// ============================================================
//  配置管理 - 自动同步
// ============================================================

function getSettingsHash(settings) {
    return JSON.stringify(settings);
}

function loadSettings() {
    return fetch('api.php?action=get_settings')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 200) {
                var newHash = getSettingsHash(data.data);
                if (newHash !== state.lastSettingsHash) {
                    state.lastSettingsHash = newHash;
                    state.settings = data.data;
                    applySettings(data.data);
                    return true;
                }
                return false;
            }
            return false;
        })
        .catch(function(e) {
            console.warn('加载设置失败', e);
            return false;
        });
}

function applySettings(settings) {
    // 主题色 - 控制按钮、链接、强调色
    if (settings.theme_color) {
        document.documentElement.style.setProperty('--color-accent-fg', settings.theme_color);
        document.documentElement.style.setProperty('--color-accent-subtle', settings.theme_color + '20');
    }
    // 弹窗遮罩 - 固定为高斯模糊，不从后端读取
    // 已在 CSS 中固定为: background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
    
    // 网站标题
    if (settings.site_title) {
        document.title = settings.site_title;
        var titleEl = document.getElementById('siteTitle');
        if (titleEl) titleEl.textContent = settings.site_title;
    }
    // 欢迎弹窗配置
    if (settings.welcome_modal) {
        state.welcomeModal = settings.welcome_modal;
    }
    
    console.log('✅ 配置已自动同步:', new Date().toLocaleTimeString());
}

function startAutoSync() {
    if (syncTimer) {
        clearInterval(syncTimer);
    }
    loadSettings();
    syncTimer = setInterval(function() {
        loadSettings();
    }, 5000);
}

function stopAutoSync() {
    if (syncTimer) {
        clearInterval(syncTimer);
        syncTimer = null;
    }
}

// ============================================================
//  初始化
// ============================================================
window.addEventListener('DOMContentLoaded', function() {
    detectDeviceSize();
    
    // 检查登录状态
    checkLoginStatus();
    
    startAutoSync();
    
    setTimeout(function() {
        var modal = state.welcomeModal || { enabled: true, title: '📚 欢迎', content: '欢迎使用！' };
        if (modal.enabled !== false) {
            showCustomModal(modal.title || '📚 欢迎', modal.content || '欢迎使用！');
        }
    }, 600);
    
    loadLocalDictionary();
    loadStarsFromCache();
    setupDragAndDrop();
    window.addEventListener('resize', detectDeviceSize);
});

window.addEventListener('beforeunload', function() {
    stopAutoSync();
});

// ============================================================
//  设备检测 & 布局切换
// ============================================================
function detectDeviceSize() {
    state.isMobile = window.innerWidth <= 768;
    if (!state.isMobile) {
        var articleCol = document.getElementById('mainArticleCol');
        var sidebarCol = document.getElementById('sidebarCol');
        if (articleCol) articleCol.style.display = 'block';
        if (sidebarCol) sidebarCol.style.display = 'block';
        closeMobileDetailSheet();
    } else {
        switchMobileLayout('article');
    }
}

function switchMobileLayout(target) {
    var articleCol = document.getElementById('mainArticleCol');
    var sidebarCol = document.getElementById('sidebarCol');
    var btns = document.querySelectorAll('.mobile-tab-btn');
    btns.forEach(function(btn) { btn.classList.remove('active'); });
    if (target === 'article') {
        if (articleCol) articleCol.style.display = 'block';
        if (sidebarCol) sidebarCol.style.display = 'none';
        if (btns[0]) btns[0].classList.add('active');
    } else {
        if (articleCol) articleCol.style.display = 'none';
        if (sidebarCol) sidebarCol.style.display = 'block';
        if (btns[1]) btns[1].classList.add('active');
    }
}

function initAudio() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
}

// ============================================================
//  词库加载
// ============================================================
function loadLocalDictionary() {
    fetch('miaoda-code.txt')
        .then(function(response) {
            if (!response.ok) throw new Error('Not found');
            return response.text();
        })
        .then(function(text) {
            parseDictionary(text);
            updateDictStatus(true, '自动加载成功');
        })
        .catch(function(err) {
            console.warn(err);
            updateDictStatus(false, '未自动加载');
            var flash = document.getElementById('corsFlash');
            if (flash) flash.classList.remove('hidden');
        });
}

document.getElementById('dictFileInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(evt) {
        parseDictionary(evt.target.result);
        updateDictStatus(true, '手动加载: ' + file.name);
        var flash = document.getElementById('corsFlash');
        if (flash) flash.classList.add('hidden');
    };
    reader.readAsText(file);
});

function parseDictionary(text) {
    state.dictionary = {};
    var lines = text.split(/\r?\n/);
    lines.forEach(function(line) {
        line = line.trim();
        if (!line || line.charAt(0) === '#') return;
        var word = '', definition = '';
        if (line.indexOf('\t') !== -1) {
            var parts = line.split('\t');
            word = parts[0].trim();
            definition = parts.slice(1).join('\t').trim();
        } else {
            var match = line.match(/^([a-zA-Z'\-]+)\s*[:：,，\s]\s*(.+)$/);
            if (match) {
                word = match[1].trim();
                definition = match[2].trim();
            } else {
                word = line;
                definition = '（词库包含该词）';
            }
        }
        if (word) state.dictionary[word.toLowerCase()] = definition;
    });
    updateDictStatus(true, '词库加载 (' + Object.keys(state.dictionary).length + ' 词)');
}

function updateDictStatus(success, message) {
    var label = document.getElementById('dictLoadLabel');
    if (label) {
        label.innerText = message;
        label.style.color = success ? '#3fb950' : '#d29922';
    }
}

function retrieveTranslation(word) {
    var w = word.toLowerCase();
    if (state.dictionary[w]) return state.dictionary[w];
    if (w.endsWith('s') && state.dictionary[w.slice(0, -1)]) return state.dictionary[w.slice(0, -1)];
    if (w.endsWith('ed') && state.dictionary[w.slice(0, -2)]) return state.dictionary[w.slice(0, -2)];
    if (w.endsWith('ing') && state.dictionary[w.slice(0, -3)]) return state.dictionary[w.slice(0, -3)];
    return null;
}

// ============================================================
//  文章生成
// ============================================================
function generateReaderView() {
    var textContent = document.getElementById('articleInput').value;
    if (!textContent.trim()) {
        showCustomModal('提示', '请先输入你的英语文章！');
        return;
    }
    var readerText = document.getElementById('readerText');
    readerText.innerHTML = '';
    var tokens = textContent.match(/([a-zA-Z'\-]+|[^a-zA-Z'\-]+)/g);
    if (!tokens) return;
    var wordCount = 0;
    tokens.forEach(function(token) {
        var isWord = /^[a-zA-Z'\-]+$/.test(token);
        if (isWord) {
            wordCount++;
            var span = document.createElement('span');
            span.className = 'word';
            span.innerText = token;
            if (!state.isMobile) span.setAttribute('draggable', 'true');
            var translation = retrieveTranslation(token);
            if (translation) {
                span.classList.add('dict-match');
                span.setAttribute('data-definition', translation);
            }
            span.addEventListener('dragstart', function(e) {
                initAudio();
                e.dataTransfer.setData('text/plain', token);
                e.dataTransfer.effectAllowed = 'copy';
                span.style.opacity = '0.5';
            });
            span.addEventListener('dragend', function() {
                span.style.opacity = '1';
            });
            span.addEventListener('click', function(e) { handleWordClick(e, span, token); });
            span.addEventListener('dblclick', function() { toggleStarWord(token); });
            readerText.appendChild(span);
        } else {
            readerText.appendChild(document.createTextNode(token));
        }
    });
    document.getElementById('wordsCountLabel').innerText = '共 ' + wordCount + ' 词';
    document.getElementById('importPanel').classList.add('hidden');
    document.getElementById('readerView').classList.remove('hidden');
    switchMode('study');
    if (state.isMobile) switchMobileLayout('article');
}

function backToEdit() {
    document.getElementById('readerView').classList.add('hidden');
    document.getElementById('importPanel').classList.remove('hidden');
}

function setupDragAndDrop() {
    var dropZone = document.getElementById('starredDropZone');
    dropZone.addEventListener('dragover', function(e) {
        if (!state.isMobile) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            dropZone.classList.add('drag-over');
        }
    });
    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('drag-over');
    });
    dropZone.addEventListener('drop', function(e) {
        if (state.isMobile) return;
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        var droppedWord = e.dataTransfer.getData('text/plain');
        if (droppedWord && /^[a-zA-Z'\-]+$/.test(droppedWord.trim())) {
            addWordToStarred(droppedWord.trim());
        }
    });
}

// ============================================================
//  单词交互
// ============================================================
function handleWordClick(event, element, rawWord) {
    initAudio();
    if (state.activeMode === 'study') {
        document.querySelectorAll('.word').forEach(function(w) { w.classList.remove('selected-highlight'); });
        element.classList.add('selected-highlight');
        state.currentSelectedWord = rawWord;
        var panel = document.getElementById('wordDetailPanel');
        panel.classList.add('details-updating');
        setTimeout(function() {
            document.getElementById('selectedWord').innerText = rawWord;
            var dbDef = retrieveTranslation(rawWord);
            document.getElementById('selectedDef').innerText = dbDef ? dbDef : '⚠️ 不在词库中。';
            updateStarButtonIcon(rawWord);
            panel.classList.remove('details-updating');
        }, 150);
        speakWord(rawWord);
        if (state.isMobile) showMobileDetailSheet();
    } else if (state.activeMode === 'recite') {
        if (state.hideDictWords && element.classList.contains('dict-match')) {
            element.classList.toggle('revealed');
        } else {
            element.classList.toggle('manual-hidden');
        }
    }
}

function showMobileDetailSheet() {
    document.getElementById('bottomBackdrop').classList.add('active');
    document.getElementById('wordDetailBox').style.display = 'block';
    setTimeout(function() {
        document.getElementById('wordDetailBox').classList.add('active');
    }, 30);
}

function closeMobileDetailSheet() {
    document.getElementById('wordDetailBox').classList.remove('active');
    document.getElementById('bottomBackdrop').classList.remove('active');
    document.querySelectorAll('.word').forEach(function(w) { w.classList.remove('selected-highlight'); });
}

function speakWord(word) {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        var textAudio = new SpeechSynthesisUtterance(word);
        textAudio.lang = 'en-US';
        window.speechSynthesis.speak(textAudio);
    }
}

function speakSelectedWord() {
    if (state.currentSelectedWord) {
        speakWord(state.currentSelectedWord);
    } else {
        showCustomModal('提示', '请先选择一个单词！');
    }
}

function adjustFontSize(delta) {
    state.fontSize = Math.max(14, Math.min(32, state.fontSize + delta));
    document.getElementById('readerText').style.fontSize = state.fontSize + 'px';
    document.getElementById('fontSizeDisplay').innerText = state.fontSize + 'px';
}

function switchMode(mode) {
    state.activeMode = mode;
    var textPanel = document.getElementById('readerText');
    var reciteControls = document.getElementById('reciteControls');
    var hintText = document.getElementById('modeHintText');
    document.getElementById('tabStudy').classList.toggle('selected', mode === 'study');
    document.getElementById('tabRecite').classList.toggle('selected', mode === 'recite');
    textPanel.classList.remove('mode-study', 'mode-recite');
    textPanel.classList.add(mode === 'study' ? 'mode-study' : 'mode-recite');
    document.querySelectorAll('.word').forEach(function(w) {
        w.classList.remove('selected-highlight');
        if (mode === 'study' && !state.isMobile) {
            w.setAttribute('draggable', 'true');
        } else {
            w.removeAttribute('draggable');
        }
    });
    if (mode === 'study') {
        reciteControls.classList.add('hidden');
        hintText.innerHTML = '<strong>学习模式提示：</strong> 点击词汇可查看释义及朗读。双击单词或<strong>拖拽至生词本</strong>可收藏。';
    } else {
        reciteControls.classList.remove('hidden');
        hintText.innerHTML = '<strong>背诵与挖空模式：</strong> 开启"一键挖空"盖住词典词，点击可揭开。点击其他词可单独模糊。';
    }
}

function toggleHideDict() {
    state.hideDictWords = document.getElementById('chkHideDict').checked;
    var container = document.getElementById('readerText');
    container.classList.toggle('hide-dict-active', state.hideDictWords);
    if (!state.hideDictWords) {
        document.querySelectorAll('.word.dict-match').forEach(function(w) { w.classList.remove('revealed'); });
    }
}

// ============================================================
//  生词本 - 核心操作（已集成云端）
// ============================================================

function loadStarsFromCache() {
    var cached = localStorage.getItem('star_words_list');
    if (cached) {
        try {
            state.stars = new Set(JSON.parse(cached));
            renderStarredList();
            updateWordCountBadge();
        } catch(e) {}
    }
}

function saveStarsToCache() {
    localStorage.setItem('star_words_list', JSON.stringify(Array.from(state.stars)));
    renderStarredList();
    updateWordCountBadge();
}

// 添加单词到生词本（集成云端）
function addWordToStarred(word) {
    var w = word.toLowerCase();
    if (state.stars.has(w)) {
        showCustomModal('提示', '该单词已在单词本中');
        return;
    }
    
    addWordWithCloud(w).then(function(result) {
        if (result.success) {
            // 已经在 addWordWithCloud 中添加了
            if (!state.stars.has(w)) {
                state.stars.add(w);
                playAddSound();
                saveStarsToCache();
            }
            renderStarredList();
            updateWordCountBadge();
            if (user.loggedIn) {
                loadCloudWords();
            }
            // 更新星标按钮
            if (state.currentSelectedWord && state.currentSelectedWord.toLowerCase() === w) {
                updateStarButtonIcon(state.currentSelectedWord);
            }
        } else {
            showCustomModal('⚠️ 提示', result.message);
        }
    });
}

// 切换单词收藏状态（集成云端）
function toggleStarWord(word) {
    var w = word.toLowerCase();
    if (state.stars.has(w)) {
        // 删除
        deleteWordWithCloud(w).then(function(result) {
            if (result.success) {
                renderStarredList();
                updateWordCountBadge();
                if (user.loggedIn) {
                    loadCloudWords();
                }
                updateStarButtonIcon(w);
            } else {
                showCustomModal('⚠️ 提示', result.message || '删除失败');
            }
        });
    } else {
        addWordToStarred(w);
    }
}

function toggleStarSelectedWord() {
    if (!state.currentSelectedWord) {
        showCustomModal('提示', '请先选中一个单词');
        return;
    }
    toggleStarWord(state.currentSelectedWord);
}

function updateStarButtonIcon(word) {
    var w = word.toLowerCase();
    var btn = document.getElementById('btnStarWord');
    if (state.stars.has(w)) {
        btn.style.color = '#e3b341';
        btn.innerText = '★';
    } else {
        btn.style.color = 'var(--color-fg-muted)';
        btn.innerText = '☆';
    }
}

function renderStarredList() {
    var listEl = document.getElementById('starredList');
    var mobileCount = document.getElementById('mobileStarCount');
    if (mobileCount) mobileCount.innerText = state.stars.size;
    updateWordCountBadge();
    
    if (state.stars.size === 0) {
        listEl.innerHTML = '<li style="padding: 16px; color: var(--color-fg-muted); text-align: center; font-size: 13px;" id="emptyListPlaceholder">生词本为空</li>';
        return;
    }
    var placeholder = document.getElementById('emptyListPlaceholder');
    if (placeholder) placeholder.remove();
    var currentContainers = listEl.querySelectorAll('.starred-item-container');
    currentContainers.forEach(function(container) {
        var w = container.getAttribute('data-word');
        if (!state.stars.has(w)) container.remove();
    });
    state.stars.forEach(function(word) {
        if (listEl.querySelector('.starred-item-container[data-word="' + word + '"]')) return;
        var container = document.createElement('li');
        container.className = 'starred-item-container slide-in';
        container.setAttribute('data-word', word);
        var def = retrieveTranslation(word) || '（无释义）';
        container.innerHTML = 
            '<div class="starred-item-content">' +
                '<span class="starred-item-word">' + word + '</span>' +
                '<div class="d-flex align-center">' +
                    '<span class="starred-item-def" title="' + def + '">' + def + '</span>' +
                    '<button class="btn-inline-delete" title="删除">×</button>' +
                '</div>' +
            '</div>' +
            '<div class="starred-item-delete-btn">删除</div>';
        var contentEl = container.querySelector('.starred-item-content');
        var deleteBtn = container.querySelector('.starred-item-delete-btn');
        var inlineXBtn = container.querySelector('.btn-inline-delete');
        bindSwipeAction(container, contentEl, deleteBtn, word);
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            handleWordDeletedAction(container, word);
        });
        inlineXBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            handleWordDeletedAction(container, word);
        });
        listEl.appendChild(container);
    });
}

function bindSwipeAction(containerEl, contentEl, deleteBtnEl, word) {
    var startX = 0, currentTranslate = 0, isDragging = false, diffX = 0, hasSwiped = false;
    var threshold = -35, maxOpenWidth = -75;
    contentEl.addEventListener('pointerdown', function(e) {
        initAudio();
        isDragging = true;
        startX = e.clientX;
        currentTranslate = containerEl.classList.contains('swiped') ? maxOpenWidth : 0;
        contentEl.style.transition = 'none';
        deleteBtnEl.style.transition = 'none';
        contentEl.setPointerCapture(e.pointerId);
        hasSwiped = false;
    });
    contentEl.addEventListener('pointermove', function(e) {
        if (!isDragging) return;
        diffX = e.clientX - startX;
        if (Math.abs(diffX) > 8) hasSwiped = true;
        var newX = currentTranslate + diffX;
        if (newX < maxOpenWidth) {
            newX = maxOpenWidth + (newX - maxOpenWidth) * 0.3;
        } else if (newX > 0) {
            newX = newX * 0.3;
        }
        contentEl.style.transform = 'translateX(' + newX + 'px)';
        deleteBtnEl.style.transform = 'translateX(' + (75 + newX) + 'px)';
    });
    contentEl.addEventListener('pointerup', function(e) {
        if (!isDragging) return;
        isDragging = false;
        contentEl.releasePointerCapture(e.pointerId);
        contentEl.style.transition = '';
        deleteBtnEl.style.transition = '';
        if (hasSwiped && diffX < -15) {
            document.querySelectorAll('.starred-item-container').forEach(function(c) {
                if (c !== containerEl && c.classList.contains('swiped')) {
                    c.classList.remove('swiped');
                    c.querySelector('.starred-item-content').style.transform = '';
                    c.querySelector('.starred-item-delete-btn').style.transform = '';
                }
            });
        }
        var finalX = currentTranslate + diffX;
        if (finalX < threshold) {
            containerEl.classList.add('swiped');
            contentEl.style.transform = 'translateX(' + maxOpenWidth + 'px)';
            deleteBtnEl.style.transform = 'translateX(0px)';
        } else {
            containerEl.classList.remove('swiped');
            contentEl.style.transform = '';
            deleteBtnEl.style.transform = '';
        }
        if (!hasSwiped) clickStarredWord(word);
    });
}

function handleWordDeletedAction(containerEl, word) {
    var w = word.toLowerCase();
    // 使用云端删除逻辑
    deleteWordWithCloud(w).then(function(result) {
        if (result.success) {
            containerEl.style.opacity = '0';
            setTimeout(function() {
                containerEl.style.height = '0px';
                containerEl.style.border = 'none';
            }, 100);
            setTimeout(function() {
                renderStarredList();
                updateWordCountBadge();
                if (user.loggedIn) {
                    loadCloudWords();
                }
                if (state.currentSelectedWord && state.currentSelectedWord.toLowerCase() === w) {
                    updateStarButtonIcon(state.currentSelectedWord);
                }
            }, 300);
        } else {
            showCustomModal('⚠️ 提示', result.message || '删除失败');
            // 恢复显示
            containerEl.classList.remove('swiped');
            var content = containerEl.querySelector('.starred-item-content');
            var deleteBtn = containerEl.querySelector('.starred-item-delete-btn');
            content.style.transform = '';
            deleteBtn.style.transform = '';
        }
    });
}

function clickStarredWord(word) {
    if (state.isMobile) {
        closeMobileDetailSheet();
        switchMobileLayout('article');
    }
    var spans = document.querySelectorAll('.word');
    for (var i = 0; i < spans.length; i++) {
        if (spans[i].innerText.toLowerCase() === word.toLowerCase()) {
            spans[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
            spans[i].classList.add('selected-highlight');
            spans[i].click();
            break;
        }
    }
}

// 清空生词本（集成云端）
function clearStarred() {
    clearCloudWords();
}

function exportStarred() {
    if (state.stars.size === 0) {
        showCustomModal('提示', '生词本为空。');
        return;
    }
    var output = '=== 我的生词本 ===\r\n';
    state.stars.forEach(function(word) {
        var def = retrieveTranslation(word) || '';
        output += word + '\t' + def + '\r\n';
    });
    var blob = new Blob([output], { type: 'text/plain;charset=utf-8' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'my-starred-words.txt';
    link.click();
    URL.revokeObjectURL(link.href);
}

// ============================================================
//  音效
// ============================================================
function playAddSound() {
    initAudio();
    if (!audioCtx) return;
    var ctx = audioCtx, time = ctx.currentTime;
    var osc = ctx.createOscillator(), gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.setValueAtTime(550, time);
    osc.frequency.exponentialRampToValueAtTime(900, time + 0.16);
    gain.gain.setValueAtTime(0, time);
    gain.gain.linearRampToValueAtTime(0.08, time + 0.03);
    gain.gain.exponentialRampToValueAtTime(0.001, time + 0.2);
    osc.start(time);
    osc.stop(time + 0.2);
}

function playDeleteSound() {
    initAudio();
    if (!audioCtx) return;
    var ctx = audioCtx, time = ctx.currentTime;
    var osc1 = ctx.createOscillator(), gain1 = ctx.createGain();
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.type = 'triangle';
    osc1.frequency.setValueAtTime(320, time);
    osc1.frequency.exponentialRampToValueAtTime(70, time + 0.12);
    gain1.gain.setValueAtTime(0.18, time);
    gain1.gain.exponentialRampToValueAtTime(0.001, time + 0.12);
    osc1.start(time);
    osc1.stop(time + 0.12);
    setTimeout(function() {
        var osc2 = ctx.createOscillator(), gain2 = ctx.createGain();
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(800, ctx.currentTime);
        osc2.frequency.setValueAtTime(380, ctx.currentTime + 0.04);
        gain2.gain.setValueAtTime(0.05, ctx.currentTime);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.05);
        osc2.start();
        osc2.stop(ctx.currentTime + 0.05);
    }, 25);
}

// ============================================================
//  演示 & 弹窗
// ============================================================
function loadSampleText() {
    document.getElementById('articleInput').value = 
'The Web is more than just a technology; it is a repository of human knowledge. Learning a foreign language can be quite challenging, but with the right tools it becomes an adventure. \n\nWhen you read articles on websites, you may encounter new vocabulary. By using our memorization system, those difficult words will be highlighted automatically.\n\nRegular practice makes perfect. Try clicking words inside the study mode to read translation details or test yourself using the recipe modes. You will eventually memorize whole paragraphs with ease!';
}

function showCustomModal(title, body) {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalBody').innerText = body;
    document.getElementById('globalModal').classList.add('active');
}

function closeModal() {
    document.getElementById('globalModal').classList.remove('active');
}

document.getElementById('globalModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});