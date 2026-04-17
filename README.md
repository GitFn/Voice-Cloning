# PHP语音克隆API - 生产环境版

这是一个基于PHP开发的高质量语音克隆API服务，集成了ElevenLabs API，可以通过上传5-60秒的音频来克隆用户的声音，并提供文字转语音功能。

#官方网站：https://81B.net

## 🚀 功能特性

### ✅ 核心功能
- **用户认证系统**：安全的注册/登录机制（JWT认证）
- **语音模型创建**：仅需5-60秒音频即可训练高质量语音模型
- **文字转语音**：使用克隆的语音生成自然流畅的音频
- **历史记录管理**：记录所有生成的语音，方便查询和管理

### 🛡️ 安全特性
- **生产环境优化**：错误日志记录、安全头设置、输入验证
- **文件存储安全**：目录权限控制、HTTPS强制（建议）
- **数据安全**：密码加密存储、JWT令牌认证
- **API安全**：请求限制、参数验证、异常处理

### 📦 技术特点
- **无数据库依赖**：使用JSON文件存储，方便部署和迁移
- **单文件核心**：主要功能集中在一个PHP文件中
- **响应式前端**：基于Bootstrap的现代化界面
- **跨平台支持**：兼容Linux和Windows服务器

## 📋 系统要求

- **PHP版本**：7.4或更高版本
- **Web服务器**：Apache 2.4+ 或 Nginx
- **PHP扩展**：curl, json, mbstring, fileinfo

### 关于FFmpeg

FFmpeg可以提供更准确的音频分析效果，但**不是必须安装**！

- ✅ 如果安装了FFmpeg：系统会自动使用FFmpeg进行精确的音频时长检测
- ⚠️ 如果未安装FFmpeg：系统会自动回退到纯PHP方法进行近似检测
- 📥 提供了**自动安装脚本**，让您轻松安装FFmpeg

## 🚀 快速开始

### 1. 下载文件

将以下三个文件上传到您的Web服务器目录：

```
├── single_api.php    # 后端API核心文件
├── config.php        # 配置文件
└── simple_index.html # 前端测试页面
```

### 3. （可选）安装FFmpeg（推荐）

我们提供了自动安装脚本，让您轻松安装FFmpeg：

**方法1：在浏览器中运行**
```
http://your-domain.com/install_ffmpeg.php
```

**方法2：在命令行中运行**
```bash
cd /path/to/your/web/directory
php install_ffmpeg.php
```

> **注意**：
> - Windows系统需要管理员权限
> - Linux/macOS系统可能需要使用sudo
> - 安装完成后建议重启Web服务器

### 4. 配置API

编辑 `config.php` 文件，修改以下关键配置：

```php
return [
    // 1. 设置ElevenLabs API密钥
    'api' => [
        'elevenlabs' => [
            'api_key' => 'your-elevenlabs-api-key', // 替换为您的密钥
            'base_url' => 'https://api.elevenlabs.io/v1',
        ],
    ],
    
    // 2. 设置安全的JWT密钥
    'jwt' => [
        'secret' => 'your-jwt-secret-key-change-me', // 强烈建议修改为复杂密钥
        'expires_in' => 3600 * 24, // 令牌过期时间（秒）
    ],
];
```

### 5. 获取ElevenLabs API密钥

1. 访问 [ElevenLabs官网](https://elevenlabs.io/)
2. 注册并登录账号
3. 在Dashboard中找到API Keys
4. 创建新的API密钥并复制

### 6. 设置目录权限

确保以下目录有写入权限：
```bash
# Linux系统
chmod 755 /path/to/your/web/directory
chmod 755 /path/to/your/web/directory/storage
chmod 755 /path/to/your/web/directory/uploads
chmod 755 /path/to/your/web/directory/logs
```

## 📖 使用指南

### 1. 访问前端页面

在浏览器中访问：
```
http://your-domain.com/simple_index.html
```

### 2. 用户注册/登录

- **注册**：输入用户名、邮箱和密码，点击"注册"按钮
- **登录**：使用已注册的邮箱和密码登录

### 3. 创建语音模型

1. 输入模型名称
2. 上传5-60秒的干净音频文件（建议使用清晰的录音）
3. 点击"创建模型"按钮
4. 等待模型创建完成（通常需要几秒钟）

### 4. 文字转语音

1. 在"我的语音模型"下拉菜单中选择已创建的模型
2. 在输入框中输入要转换的文字
3. 点击"生成语音"按钮
4. 等待语音生成完成，然后点击播放按钮听取

### 5. 查看历史记录

- 页面底部会显示所有生成的语音历史
- 可以直接在历史记录中播放之前生成的语音

## 📡 API接口文档

### 1. 健康检查

**URL:** `/single_api.php/api/health`
**方法:** `GET`
**描述:** 检查API服务是否正常运行
**响应:**
```json
{
    "status": "success",
    "message": "API服务正常运行",
    "timestamp": "2023-06-15 10:00:00"
}
```

### 2. 用户注册

**URL:** `/single_api.php/api/register`
**方法:** `POST`
**参数:**
- `username`: 用户名
- `email`: 邮箱地址
- `password`: 密码（至少6位）

**响应:**
```json
{
    "status": "success",
    "message": "注册成功",
    "data": {
        "token": "jwt-token-here"
    },
    "timestamp": "2023-06-15 10:00:00"
}
```

### 3. 用户登录

**URL:** `/single_api.php/api/login`
**方法:** `POST`
**参数:**
- `email`: 邮箱地址
- `password`: 密码

**响应:**
```json
{
    "status": "success",
    "message": "登录成功",
    "data": {
        "token": "jwt-token-here"
    },
    "timestamp": "2023-06-15 10:00:00"
}
```

### 4. 创建语音模型

**URL:** `/single_api.php/api/voices`
**方法:** `POST`
**认证:** 需要JWT令牌（在请求头中添加 `Authorization: Bearer your-token`）
**参数:**
- `name`: 模型名称（可选，默认：My Voice）
- `audio`: 音频文件（5-60秒，支持mp3、wav、ogg、m4a）

**响应:**
```json
{
    "status": "success",
    "message": "语音模型创建成功",
    "data": {
        "voice_model_id": 1,
        "elevenlabs_voice_id": "voice-id-from-elevenlabs",
        "name": "My Voice",
        "status": "ready"
    },
    "timestamp": "2023-06-15 10:00:00"
}
```

### 5. 获取语音模型列表

**URL:** `/single_api.php/api/voices`
**方法:** `GET`
**认证:** 需要JWT令牌

**响应:**
```json
{
    "status": "success",
    "message": "获取语音模型列表成功",
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "name": "My Voice",
            "elevenlabs_voice_id": "voice-id-from-elevenlabs",
            "sample_count": 1,
            "status": "ready",
            "created_at": "2023-06-15 10:00:00",
            "updated_at": "2023-06-15 10:00:00"
        }
    ],
    "timestamp": "2023-06-15 10:00:00"
}
```

### 6. 文字转语音

**URL:** `/single_api.php/api/tts`
**方法:** `POST`
**认证:** 需要JWT令牌
**参数:**
- `voice_model_id`: 语音模型ID
- `text`: 要转换的文字（最多5000字符）

**响应:**
```json
{
    "status": "success",
    "message": "语音生成成功",
    "data": {
        "audio_url": "http://your-domain.com/uploads/tts_1234567890.mp3",
        "filename": "tts_1234567890.mp3",
        "voice_model_id": 1
    },
    "timestamp": "2023-06-15 10:00:00"
}
```

### 7. 获取TTS历史记录

**URL:** `/single_api.php/api/tts/history`
**方法:** `GET`
**认证:** 需要JWT令牌

**响应:**
```json
{
    "status": "success",
    "message": "获取历史记录成功",
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "voice_model_id": 1,
            "text": "这是一个测试",
            "audio_filename": "tts_1234567890.mp3",
            "audio_url": "http://your-domain.com/uploads/tts_1234567890.mp3",
            "created_at": "2023-06-15 10:00:00"
        }
    ],
    "timestamp": "2023-06-15 10:00:00"
}
```

## 🔧 高级配置

### 1. 存储目录配置

可以修改 `config.php` 中的存储目录：

```php
'storage' => [
    'directory' => '/path/to/custom/storage/', // 自定义存储目录
],
'upload' => [
    'directory' => '/path/to/custom/uploads/', // 自定义上传目录
    'max_size' => 20 * 1024 * 1024, // 最大文件大小（20MB）
    'allowed_types' => ['mp3', 'wav'], // 限制允许的格式
],
```

### 2. 日志配置

日志文件默认存储在 `logs/` 目录下：
- `errors.log`: 错误日志
- `activity.log`: 操作日志

### 3. Nginx配置示例

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your/web/directory;
    
    index index.html index.php;
    
    # PHP配置
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
    
    # 静态文件缓存
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|mp3|wav|ogg|m4a)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
    }
    
    # 安全头
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";
}
```

## 📝 注意事项

1. **音频质量要求**：
   - 音频时长：5-60秒
   - 音质：清晰无杂音
   - 格式：支持mp3、wav、ogg、m4a

2. **生产环境建议**：
   - 使用HTTPS加密传输
   - 定期备份 `storage/` 和 `uploads/` 目录
   - 限制API访问频率（可通过Web服务器配置）
   - 定期清理过期的音频文件

3. **API密钥安全**：
   - 不要在代码中硬编码API密钥
   - 定期更换API密钥
   - 不要将API密钥分享给他人

4. **性能优化**：
   - 对于高流量场景，建议使用Redis缓存
   - 考虑使用CDN加速静态文件访问
   - 定期优化存储的JSON文件

## ❗ 常见问题

### Q: 上传音频时提示"文件验证失败"？
A: 请确保音频文件满足以下条件：
- 时长在5-60秒之间
- 大小不超过配置的最大限制
- 格式为支持的音频格式
- 是真实的音频文件（不是重命名的其他文件）

### Q: 生成语音时提示"API请求失败"？
A: 请检查：
- ElevenLabs API密钥是否正确
- 网络连接是否正常
- ElevenLabs API服务是否正常
- 语音模型是否已创建成功

### Q: 登录后无法访问其他功能？
A: 请检查：
- JWT令牌是否有效
- 浏览器是否启用了本地存储（localStorage）
- 服务器时间是否正确

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

## 🤝 贡献

欢迎提交Issue和Pull Request！

## 📞 支持

如果您在使用过程中遇到问题，可以：
1. 查看错误日志（`logs/errors.log`）
2. 检查API响应的错误信息
3. 确保所有依赖都已正确安装

---

**祝您使用愉快！** 🎉
