<?php
/**
 * PHP语音克隆API - 生产环境版
 * 基于ElevenLabs API实现高质量语音克隆
 */

// 生产环境安全设置
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/errors.log');

// 跨域和内容类型设置
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

// ====================== 配置加载 ======================
$CONFIG = require __DIR__ . '/config.php';

// ====================== 工具类 ======================

/**
 * 文件存储类 - 安全的JSON文件存储
 */
class SecureStorage {
    private $storage_dir;
    private $lock_file;

    public function __construct($config) {
        $this->storage_dir = rtrim($config['storage']['directory'], '/') . '/';
        $this->lock_file = $this->storage_dir . 'storage.lock';
        
        // 创建存储目录和日志目录
        $this->createDirectories();
        
        // 确保目录权限安全
        $this->secureDirectories();
    }

    // 创建必要的目录
    private function createDirectories() {
        $directories = [
            $this->storage_dir,
            __DIR__ . '/logs/',
            $CONFIG['upload']['directory']
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    // 确保目录权限安全
    private function secureDirectories() {
        // 确保存储目录不可被web访问
        $htaccess = $this->storage_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        
        $htaccess = $CONFIG['upload']['directory'] . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n");
        }
    }

    // 获取表的所有数据（带文件锁）
    private function getTableData($table_name) {
        $file_path = $this->storage_dir . $table_name . '.json';
        
        if (!file_exists($file_path)) {
            return [];
        }
        
        // 加读锁
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new Exception("无法打开存储文件");
        }
        
        flock($handle, LOCK_SH);
        $content = fread($handle, filesize($file_path));
        flock($handle, LOCK_UN);
        fclose($handle);
        
        return json_decode($content, true) ?: [];
    }

    // 保存表的数据（带文件锁）
    private function saveTableData($table_name, $data) {
        $file_path = $this->storage_dir . $table_name . '.json';
        $temp_file = $file_path . '.tmp';
        
        // 写入临时文件
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($temp_file, $content) === false) {
            throw new Exception("无法写入存储文件");
        }
        
        // 原子替换
        if (!rename($temp_file, $file_path)) {
            unlink($temp_file);
            throw new Exception("无法更新存储文件");
        }
        
        return true;
    }

    // 插入数据
    public function insert($table_name, $data) {
        $this->validateTableName($table_name);
        $this->sanitizeData($data);
        
        $table_data = $this->getTableData($table_name);
        
        // 生成安全的ID
        $id = count($table_data) > 0 ? max(array_column($table_data, 'id')) + 1 : 1;
        $data['id'] = $id;
        
        // 添加时间戳
        $now = date('Y-m-d H:i:s');
        if (!isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }
        
        $table_data[] = $data;
        $this->saveTableData($table_name, $table_data);
        
        return $id;
    }

    // 查询数据
    public function fetch($table_name, $conditions = []) {
        $this->validateTableName($table_name);
        $this->sanitizeData($conditions);
        
        $table_data = $this->getTableData($table_name);
        
        foreach ($table_data as $row) {
            $match = true;
            foreach ($conditions as $key => $value) {
                if (!isset($row[$key]) || $row[$key] != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $row;
            }
        }
        
        return null;
    }

    // 查询所有匹配的数据
    public function fetchAll($table_name, $conditions = [], $order_by = null, $order_dir = 'DESC') {
        $this->validateTableName($table_name);
        $this->sanitizeData($conditions);
        
        $table_data = $this->getTableData($table_name);
        
        // 过滤数据
        $filtered_data = [];
        foreach ($table_data as $row) {
            $match = true;
            foreach ($conditions as $key => $value) {
                if (!isset($row[$key]) || $row[$key] != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $filtered_data[] = $row;
            }
        }
        
        // 排序
        if ($order_by && !empty($filtered_data)) {
            usort($filtered_data, function($a, $b) use ($order_by, $order_dir) {
                if (!isset($a[$order_by]) || !isset($b[$order_by])) {
                    return 0;
                }
                if ($order_dir === 'DESC') {
                    return $b[$order_by] <=> $a[$order_by];
                } else {
                    return $a[$order_by] <=> $b[$order_by];
                }
            });
        }
        
        return $filtered_data;
    }

    // 更新数据
    public function update($table_name, $conditions, $data) {
        $this->validateTableName($table_name);
        $this->sanitizeData($conditions);
        $this->sanitizeData($data);
        
        $table_data = $this->getTableData($table_name);
        $updated = false;
        
        // 添加更新时间戳
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        foreach ($table_data as &$row) {
            $match = true;
            foreach ($conditions as $key => $value) {
                if (!isset($row[$key]) || $row[$key] != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $row = array_merge($row, $data);
                $updated = true;
            }
        }
        
        if ($updated) {
            $this->saveTableData($table_name, $table_data);
        }
        
        return $updated;
    }

    // 验证表名安全
    private function validateTableName($table_name) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
            throw new Exception("无效的表名");
        }
    }

    // 清理数据
    private function sanitizeData(&$data) {
        if (!is_array($data)) {
            return;
        }
        
        foreach ($data as &$value) {
            if (is_string($value)) {
                // 防止XSS
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $this->sanitizeData($value);
            }
        }
    }
}

/**
 * ElevenLabs API客户端 - 安全增强版
 */
class ElevenLabsAPIClient {
    private $api_key;
    private $base_url;
    private $timeout = 30;

    public function __construct($config) {
        $this->api_key = $config['api']['elevenlabs']['api_key'];
        $this->base_url = rtrim($config['api']['elevenlabs']['base_url'], '/');
        
        // 验证API密钥
        if (empty($this->api_key) || $this->api_key === 'your-elevenlabs-api-key') {
            throw new Exception("请在config.php中配置有效的ElevenLabs API密钥");
        }
    }

    // 创建新的声音模型
    public function createVoice($name, $files) {
        $url = "{$this->base_url}/voices/add";
        $data = [
            'name' => substr($name, 0, 100), // 限制长度
            'labels' => json_encode(['accent' => 'american']),
            'description' => 'Generated by PHP Voice Clone API',
        ];

        $response = $this->multipartRequest($url, $data, $files);
        return json_decode($response, true);
    }

    // 添加音频样本到现有声音模型
    public function addVoiceSample($voice_id, $file) {
        $url = "{$this->base_url}/voices/{$voice_id}/add";
        $data = [];
        $response = $this->multipartRequest($url, $data, [$file]);
        return json_decode($response, true);
    }

    // 文字转语音
    public function textToSpeech($voice_id, $text, $model_id = 'eleven_multilingual_v2') {
        $url = "{$this->base_url}/text-to-speech/{$voice_id}";
        $headers = [
            'Content-Type: application/json',
        ];
        $data = [
            'text' => substr($text, 0, 5000), // 限制文本长度
            'model_id' => $model_id,
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
                'style' => 0.0,
                'use_speaker_boost' => true
            ],
        ];

        return $this->request('POST', $url, $headers, json_encode($data), false);
    }

    // 获取声音模型信息
    public function getVoice($voice_id) {
        $url = "{$this->base_url}/voices/{$voice_id}";
        $response = $this->request('GET', $url);
        return json_decode($response, true);
    }

    // 多部分表单请求（用于文件上传）
    private function multipartRequest($url, $fields, $files) {
        $boundary = '----WebKitFormBoundary' . substr(md5(mt_rand() . microtime()), 0, 16);
        $headers = [
            "Content-Type: multipart/form-data; boundary={$boundary}",
        ];

        $data = '';
        // 添加字段
        foreach ($fields as $name => $value) {
            $data .= "--{$boundary}\r\n";
            $data .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $data .= "{$value}\r\n";
        }

        // 添加文件
        foreach ($files as $file) {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new Exception("无效的上传文件");
            }
            
            $filename = basename($file['name']);
            $filetype = mime_content_type($file['tmp_name']);
            $filedata = file_get_contents($file['tmp_name']);

            $data .= "--{$boundary}\r\n";
            $data .= "Content-Disposition: form-data; name=\"files"; filename=\"{$filename}\"\r\n";
            $data .= "Content-Type: {$filetype}\r\n\r\n";
            $data .= $filedata . "\r\n";
        }

        $data .= "--{$boundary}--\r\n";

        return $this->request('POST', $url, $headers, $data);
    }

    // 通用HTTP请求
    private function request($method, $url, $headers = [], $data = null, $return_json = true) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge([
            "xi-api-key: {$this->api_key}",
            "User-Agent: PHP-Voice-Clone-API/1.0"
        ], $headers));
        
        // 安全设置
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

        if ($data !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception("API请求失败: {$error}");
        }

        curl_close($curl);

        if ($http_code >= 400) {
            // 记录API错误
            error_log("ElevenLabs API错误 ({$http_code}): {$response}");
            
            // 解析错误信息
            $error_data = json_decode($response, true);
            $error_msg = $error_data['detail'] ?? "API错误 ({$http_code})";
            
            throw new Exception($error_msg);
        }

        if ($return_json) {
            return json_decode($response, true);
        }

        return $response;
    }
}

/**
 * JWT认证类 - 增强安全性
 */
class JWTAuthenticator {
    private $secret;
    private $expires_in;
    private $algorithm = 'HS256';

    public function __construct($config) {
        $this->secret = $config['jwt']['secret'];
        $this->expires_in = $config['jwt']['expires_in'];
        
        // 验证JWT密钥
        if (empty($this->secret) || $this->secret === 'your-jwt-secret-key') {
            throw new Exception("请在config.php中配置安全的JWT密钥");
        }
    }

    // 生成JWT令牌
    public function generateToken($user_id) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode([
            'sub' => $user_id,
            'iat' => time(),
            'exp' => time() + $this->expires_in,
            'iss' => $_SERVER['HTTP_HOST'],
            'aud' => 'voice-clone-api'
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    // 验证JWT令牌
    public function verifyToken($token) {
        if (empty($token)) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

        $header = json_decode($this->base64UrlDecode($base64UrlHeader), true);
        $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true);

        // 检查算法
        if (!isset($header['alg']) || $header['alg'] !== $this->algorithm) {
            return false;
        }

        // 检查过期时间
        if (empty($payload['exp']) || time() > $payload['exp']) {
            return false;
        }

        // 检查发行者和受众
        if (!isset($payload['iss']) || $payload['iss'] !== $_SERVER['HTTP_HOST']) {
            return false;
        }
        if (!isset($payload['aud']) || $payload['aud'] !== 'voice-clone-api') {
            return false;
        }

        // 验证签名
        $signature = $this->base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, $this->secret, true);

        return hash_equals($signature, $expectedSignature) ? $payload['sub'] : false;
    }

    // 从请求头获取令牌
    public function getTokenFromHeader() {
        $headers = $this->getAllHeaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    // 安全的base64Url编码
    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    // 安全的base64Url解码
    private function base64UrlDecode($data) {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($data);
    }

    // 获取所有请求头（兼容PHP-FPM）
    private function getAllHeaders() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// ====================== 辅助函数 ======================

/**
 * 返回JSON响应
 */
function sendResponse($status, $message, $data = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 记录日志
 */
function logActivity($message, $level = 'info') {
    $log_file = __DIR__ . '/logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * 验证上传的音频文件
 */
function validateAudioFile($file, $config) {
    // 检查文件是否上传成功
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, '文件上传失败'];
    }

    // 检查文件大小
    if ($file['size'] > $config['upload']['max_size']) {
        $max_size_mb = $config['upload']['max_size'] / (1024 * 1024);
        return [false, "文件大小不能超过{$max_size_mb}MB"];
    }

    // 检查文件类型
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $config['upload']['allowed_types'])) {
        $allowed_types = implode(', ', $config['upload']['allowed_types']);
        return [false, "只支持{$allowed_types}格式的音频文件"];
    }

    // 检查音频时长（5秒到1分钟）
    $duration = getAudioDuration($file['tmp_name']);
    if ($duration < 5 || $duration > 60) {
        return [false, '音频时长必须在5秒到1分钟之间'];
    }

    // 检查文件是否为真实音频
    $mime_type = mime_content_type($file['tmp_name']);
    if (!str_starts_with($mime_type, 'audio/')) {
        return [false, '请上传真实的音频文件'];
    }

    return [true, '文件验证通过'];
}

/**
 * 获取音频文件时长（智能切换：优先使用FFmpeg，备选纯PHP方法）
 */
function getAudioDuration($file_path) {
    // 安全检查
    if (!is_file($file_path) || !is_readable($file_path)) {
        return 0;
    }
    
    // 1. 优先尝试使用FFmpeg（如果可用）
    $duration = getAudioDurationWithFFmpeg($file_path);
    if ($duration > 0) {
        return $duration;
    }
    
    // 2. 如果FFmpeg不可用，使用纯PHP方法作为备选
    return getAudioDurationWithoutFFmpeg($file_path);
}

/**
 * 使用FFmpeg获取音频文件时长（精确）
 */
function getAudioDurationWithFFmpeg($file_path) {
    // 检查FFmpeg是否可用
    $ffmpeg_path = findFFmpeg();
    if (empty($ffmpeg_path)) {
        return 0;
    }
    
    // 安全检查
    if (!is_file($file_path) || !is_readable($file_path)) {
        return 0;
    }
    
    // 转义文件路径
    $escaped_path = escapeshellarg($file_path);
    $ffmpeg_escaped = escapeshellarg($ffmpeg_path);
    
    // 使用FFprobe获取准确时长
    $command = "{$ffmpeg_escaped} -i {$escaped_path} -show_entries format=duration -v quiet -of csv="p=0"";
    
    ob_start();
    passthru($command, $return_var);
    $output = ob_get_clean();
    
    if ($return_var !== 0) {
        return 0;
    }
    
    $duration = floatval(trim($output));
    return $duration > 0 ? $duration : 0;
}

/**
 * 使用纯PHP方法获取音频文件时长（近似）
 */
function getAudioDurationWithoutFFmpeg($file_path) {
    // 获取文件大小
    $file_size = filesize($file_path);
    if ($file_size < 100) {
        return 0;
    }
    
    // 尝试从文件头获取音频信息
    $handle = fopen($file_path, 'rb');
    if (!$handle) {
        return 0;
    }
    
    $duration = 0;
    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    switch ($file_ext) {
        case 'mp3':
            // MP3文件近似时长计算（比特率估算）
            // 假设平均比特率为128kbps
            $bitrate = 128 * 1024; // 128kbps
            $duration = ($file_size * 8) / $bitrate;
            break;
            
        case 'wav':
            // WAV文件头分析
            fseek($handle, 24);
            $bit_depth = unpack('v', fread($handle, 2))[1];
            $sample_rate = unpack('V', fread($handle, 4))[1];
            $channels = unpack('v', fread($handle, 2))[1];
            
            if ($bit_depth > 0 && $sample_rate > 0 && $channels > 0) {
                $byte_rate = $sample_rate * $channels * $bit_depth / 8;
                $duration = ($file_size - 44) / $byte_rate; // 减去WAV头44字节
            }
            break;
            
        case 'ogg':
        case 'm4a':
            // 对于OGG和M4A，使用更保守的估计
            // 假设平均比特率为128kbps
            $bitrate = 128 * 1024;
            $duration = ($file_size * 8) / $bitrate;
            break;
            
        default:
            $duration = 0;
            break;
    }
    
    fclose($handle);
    
    // 确保返回合理的时长
    return max(0, min($duration, 120)); // 限制最大120秒
}

/**
 * 查找系统中的FFmpeg可执行文件
 */
function findFFmpeg() {
    // 检查环境变量
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    
    // 可能的FFmpeg可执行文件名
    $ffmpeg_names = ['ffmpeg', 'ffmpeg.exe'];
    
    // 遍历所有路径查找FFmpeg
    foreach ($paths as $path) {
        foreach ($ffmpeg_names as $name) {
            $ffmpeg_path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (file_exists($ffmpeg_path) && is_executable($ffmpeg_path)) {
                return $ffmpeg_path;
            }
        }
    }
    
    // 检查常见安装位置
    $common_locations = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/homebrew/bin/ffmpeg', // macOS Homebrew
        'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
    ];
    
    foreach ($common_locations as $location) {
        if (file_exists($location) && is_executable($location)) {
            return $location;
        }
    }
    
    return '';
}

/**
 * 清理临时文件
 */
function cleanupTempFiles() {
    // 清理上传的临时文件
    if (isset($_FILES)) {
        foreach ($_FILES as $file_array) {
            if (is_array($file_array['tmp_name'])) {
                foreach ($file_array['tmp_name'] as $tmp_file) {
                    if (is_uploaded_file($tmp_file)) {
                        @unlink($tmp_file);
                    }
                }
            } else {
                if (is_uploaded_file($file_array['tmp_name'])) {
                    @unlink($file_array['tmp_name']);
                }
            }
        }
    }
}

// ====================== 初始化 ======================

// 注册错误处理
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        error_log("致命错误: {$error['message']} in {$error['file']} on line {$error['line']}");
        sendResponse('error', '服务器内部错误', null, 500);
    }
    // 清理临时文件
    cleanupTempFiles();
});

// 注册异常处理
try {
    // 初始化组件
    $storage = new SecureStorage($CONFIG);
    $elevenlabs = new ElevenLabsAPIClient($CONFIG);
    $jwt = new JWTAuthenticator($CONFIG);
    
    // ====================== 路由处理 ======================
    
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    
    // 公共路由：健康检查
    if ($path === '/single_api.php/api/health' && $method === 'GET') {
        sendResponse('success', 'API服务正常运行');
    }
    
    // 公共路由：用户注册
    if ($path === '/single_api.php/api/register' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 验证输入
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            sendResponse('error', '缺少必要参数', null, 400);
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            sendResponse('error', '邮箱格式无效', null, 400);
        }
        
        if (strlen($data['password']) < 6) {
            sendResponse('error', '密码长度不能少于6位', null, 400);
        }

        $username = $data['username'];
        $email = $data['email'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);

        // 检查用户名和邮箱是否已存在
        if ($storage->fetch('users', ['username' => $username])) {
            sendResponse('error', '用户名已存在', null, 409);
        }
        if ($storage->fetch('users', ['email' => $email])) {
            sendResponse('error', '邮箱已存在', null, 409);
        }
        
        $user_id = $storage->insert('users', ['username' => $username, 'email' => $email, 'password' => $password]);
        $token = $jwt->generateToken($user_id);
        
        logActivity("用户注册成功: {$email}");
        sendResponse('success', '注册成功', ['token' => $token]);
    }
    
    // 公共路由：用户登录
    if ($path === '/single_api.php/api/login' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 验证输入
        if (empty($data['email']) || empty($data['password'])) {
            sendResponse('error', '缺少必要参数', null, 400);
        }

        $email = $data['email'];
        $password = $data['password'];

        $user = $storage->fetch('users', ['email' => $email]);
        if (!$user || !password_verify($password, $user['password'])) {
            logActivity("登录失败: {$email}", 'warning');
            sendResponse('error', '邮箱或密码错误', null, 401);
        }
        
        $token = $jwt->generateToken($user['id']);
        logActivity("用户登录成功: {$email}");
        sendResponse('success', '登录成功', ['token' => $token]);
    }
    
    // 需要认证的路由
    $token = $jwt->getTokenFromHeader();
    $user_id = $jwt->verifyToken($token);
    
    if (!$user_id) {
        sendResponse('error', '未授权', null, 401);
    }
    
    // 私有路由：创建语音模型
    if ($path === '/single_api.php/api/voices' && $method === 'POST') {
        if (!isset($_FILES['audio'])) {
            sendResponse('error', '缺少音频文件', null, 400);
        }

        $audio_file = $_FILES['audio'];
        list($is_valid, $message) = validateAudioFile($audio_file, $CONFIG);
        
        if (!$is_valid) {
            sendResponse('error', $message, null, 400);
        }

        $name = isset($_POST['name']) ? $_POST['name'] : 'My Voice';
        
        // 上传文件到ElevenLabs
        $voice_response = $elevenlabs->createVoice($name, [$audio_file]);
        
        // 保存到存储
        $voice_model_id = $storage->insert('voice_models', [
            'user_id' => $user_id,
            'name' => $name,
            'elevenlabs_voice_id' => $voice_response['voice_id'],
            'status' => 'ready',
            'sample_count' => 1
        ]);
        
        // 保存音频样本
        $filename = uniqid() . '.' . strtolower(pathinfo($audio_file['name'], PATHINFO_EXTENSION));
        $destination = rtrim($CONFIG['upload']['directory'], '/') . '/' . $filename;
        
        if (!move_uploaded_file($audio_file['tmp_name'], $destination)) {
            throw new Exception("无法保存音频文件");
        }
        
        $duration = getAudioDuration($destination);
        
        $storage->insert('audio_samples', [
            'voice_model_id' => $voice_model_id,
            'filename' => $filename,
            'duration' => $duration
        ]);
        
        logActivity("用户 {$user_id} 创建语音模型成功: {$name}");
        sendResponse('success', '语音模型创建成功', [
            'voice_model_id' => $voice_model_id,
            'elevenlabs_voice_id' => $voice_response['voice_id'],
            'name' => $name,
            'status' => 'ready'
        ]);
    }
    
    // 私有路由：获取用户的所有语音模型
    if ($path === '/single_api.php/api/voices' && $method === 'GET') {
        $voices = $storage->fetchAll('voice_models', ['user_id' => $user_id]);
        sendResponse('success', '获取语音模型列表成功', $voices);
    }
    
    // 私有路由：文字转语音
    if ($path === '/single_api.php/api/tts' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 验证输入
        if (empty($data['voice_model_id']) || empty($data['text'])) {
            sendResponse('error', '缺少必要参数', null, 400);
        }

        $voice_model_id = $data['voice_model_id'];
        $text = $data['text'];

        // 检查模型是否属于用户
        $voice = $storage->fetch('voice_models', ['id' => $voice_model_id, 'user_id' => $user_id]);
        if (!$voice) {
            sendResponse('error', '语音模型不存在或无权访问', null, 404);
        }
        
        // 调用ElevenLabs API生成语音
        $audio_data = $elevenlabs->textToSpeech($voice['elevenlabs_voice_id'], $text);
        
        // 保存生成的音频
        $filename = 'tts_' . uniqid() . '.mp3';
        $destination = rtrim($CONFIG['upload']['directory'], '/') . '/' . $filename;
        
        if (file_put_contents($destination, $audio_data) === false) {
            throw new Exception("无法保存生成的音频文件");
        }
        
        // 记录到历史
        $storage->insert('tts_history', [
            'user_id' => $user_id,
            'voice_model_id' => $voice_model_id,
            'text' => $text,
            'audio_filename' => $filename
        ]);
        
        // 返回音频URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $audio_url = "{$protocol}://{$_SERVER['HTTP_HOST']}/{$CONFIG['upload']['directory']}{$filename}";
        
        logActivity("用户 {$user_id} 生成语音成功，模型ID: {$voice_model_id}");
        sendResponse('success', '语音生成成功', [
            'audio_url' => $audio_url,
            'filename' => $filename,
            'voice_model_id' => $voice_model_id
        ]);
    }
    
    // 私有路由：获取TTS历史记录
    if ($path === '/single_api.php/api/tts/history' && $method === 'GET') {
        $history = $storage->fetchAll(
            'tts_history', 
            ['user_id' => $user_id],
            'created_at',
            'DESC'
        );
        
        // 添加音频URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        foreach ($history as &$item) {
            $item['audio_url'] = "{$protocol}://{$_SERVER['HTTP_HOST']}/{$CONFIG['upload']['directory']}{$item['audio_filename']}";
        }
        
        sendResponse('success', '获取历史记录成功', $history);
    }
    
    // 404
    sendResponse('error', '接口不存在', null, 404);
    
} catch (Exception $e) {
    // 记录异常
    error_log("异常: {$e->getMessage()}" . PHP_EOL . $e->getTraceAsString());
    
    // 返回友好错误
    sendResponse('error', $e->getMessage(), null, 500);
}
