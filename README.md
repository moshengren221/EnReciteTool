# EnReciteTool
一个基于php的网页背书平台，利用DeepSeek模型进行辅助开发
## 版权与使用说明
项目作者：moshengren221
项目名称：EnReciteTool
版权所有 © 2026 moshengren221

### 允许行为
个人学习、开源交流、非盈利场景下可自由 Fork、修改、二次开发本项目，无需提前申请授权。

### 禁止行为
未经作者 moshengren221 书面许可，严禁将本项目及衍生修改版本用于任何商业场景，包括但不限于：付费售卖、商业系统内嵌、盈利类二次开发、广告变现、企业商用部署等。
如需商用授权，请通过仓库 Issues 联系作者申请。

### 项目免责声明
本项目处于持续开发阶段，功能不完善、存在未知Bug与稳定性风险：
1. 使用本项目产生的一切故障、数据丢失、经济损失等全部风险由使用者自行承担，原作者不承担任何责任；
2. 遇到Bug、使用问题时，可在仓库新建 Issue 提交详细复现步骤，供所有使用者参考讨论；作者不保证限时修复全部问题，欢迎提交PR共同完善项目。

# EnReciteTool
我们的开发目的是为了**解决英语背书困难的问题**，再找训了互联网上的多个平台后发现根本没有能满足我学习需求的背诵网站或软件，于是与deepseek一起开发了这款在线背记工具。

**For the English version, please scroll down.**

------------
## 🚩准备环境
云服务器或虚拟主机（硬盘或存储容量**≥1GB**）
配置好php环境（**PHP=7.0+**）
支持ftp和数据库（推荐PHPmyAdmin）

------------


## ⚙️如何使用/设置？
1. 将全套文件复制到你的服务器or虚拟主机下⬇️

2. 预先创建数据表

```
CREATE TABLE IF NOT EXISTS `dictionary` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `word` varchar(100) NOT NULL,
    `definition` text NOT NULL,
    `created_at` datetime,
    `updated_at` datetime,
    PRIMARY KEY (`id`),
    UNIQUE KEY `word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```


```
CREATE TABLE IF NOT EXISTS `user_words` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `word` varchar(100) NOT NULL,
    `definition` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_word` (`user_id`, `word`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

3. 进入页面可选择登录，默认密码admin123，建议立即更改！

```
更改方式：
1=>打开 根目录/login.php
2=>把admin123更改成你的秘钥即可（默认已做鉴权）
```
其他配置请自行探索👌

------------
# EnReciteTool

The primary objective of this development project is **to address the challenges associated with reciting English vocabulary**. After conducting extensive research across multiple online platforms, we found that no existing recitation website or software adequately met our learning requirements. Consequently, we collaborated with DeepSeek to develop this online memorization tool.

------------
## 🚩Prerequisites
- A cloud server or virtual hosting environment with **≥1 GB** of available storage capacity.
- A properly configured PHP environment (**PHP 7.0 or higher**).
- Support for FTP and database management (PHPMyAdmin is recommended).

------------

## ⚙️How to Use / Set Up

1. Copy the entire package of files to your server or virtual hosting directory. ⬇️

2. Pre-create the required database tables using the following SQL statements:

```
CREATE TABLE IF NOT EXISTS `dictionary` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `word` varchar(100) NOT NULL,
    `definition` text NOT NULL,
    `created_at` datetime,
    `updated_at` datetime,
    PRIMARY KEY (`id`),
    UNIQUE KEY `word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

```
CREATE TABLE IF NOT EXISTS `user_words` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `word` varchar(100) NOT NULL,
    `definition` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_word` (`user_id`, `word`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

3. Upon accessing the page, you may log in using the default password `admin123`. It is strongly recommended that you change this password immediately.

```
To change the default password:
1. Open the file located at /root/login.php.
2. Replace "admin123" with your desired secure passphrase (authentication measures are already implemented by default).
```

For additional configuration options, please explore the system at your convenience. 👌
