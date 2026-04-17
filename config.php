<?php
/**
 * PHP语音克隆API - 配置文件
 * 请根据您的实际情况修改以下配置
 */

return [
    // 存储配置
    'storage' => [
        'directory' => __DIR__ . '/storage/', // 数据存储目录
    ],
    
    // API配置
    'api' => [
        'elevenlabs' => [
            'api_key' => 'your-elevenlabs-api-key', // 替换为您的ElevenLabs API密钥
            'base_url' => 'https://api.elevenlabs.io/v1', // ElevenLabs API基础URL
        ],
    ],
    
    // 上传配置
    'upload' => [
        'directory' => __DIR__ . '/uploads/', // 音频文件上传目录
        'max_size' => 15 * 1024 * 1024, // 最大文件大小（15MB）
        'allowed_types' => ['mp3', 'wav', 'ogg', 'm4a'], // 允许的音频格式
    ],
    
    // JWT配置
    'jwt' => [
        'secret' => 'your-jwt-secret-key-change-me', // 请修改为安全的JWT密钥
        'expires_in' => 3600 * 24, // 令牌过期时间（秒），这里设置为24小时
    ],
];
