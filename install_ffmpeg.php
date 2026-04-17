<?php
/**
 * FFmpeg自动安装脚本
 * 支持Windows、Linux和macOS平台
 */

echo "========================================\n";
echo "PHP语音克隆API - FFmpeg自动安装脚本\n";
echo "========================================\n\n";

// 检查操作系统
$os = php_uname('s');
echo "检测到操作系统: {$os}\n\n";

// 根据操作系统执行不同的安装逻辑
if (str_starts_with($os, 'Windows')) {
    installFFmpegWindows();
} elseif (str_starts_with($os, 'Linux')) {
    installFFmpegLinux();
} elseif (str_starts_with($os, 'Darwin')) {
    installFFmpegMacOS();
} else {
    echo "❌ 不支持的操作系统: {$os}\n";
    echo "请手动安装FFmpeg: https://ffmpeg.org/download.html\n";
    exit(1);
}

echo "\n========================================\n";
echo "安装完成！请刷新页面测试FFmpeg功能\n";
echo "========================================\n";

/**
 * 在Windows上安装FFmpeg
 */
function installFFmpegWindows() {
    echo "正在Windows上安装FFmpeg...\n";
    
    // 检查是否已安装
    exec("where ffmpeg", $output, $return_var);
    if ($return_var === 0) {
        echo "✅ FFmpeg已安装在: {$output[0]}\n";
        return;
    }
    
    // 下载FFmpeg
    $downloadUrl = "https://github.com/GyanD/codexffmpeg/releases/download/6.1/ffmpeg-6.1-full_build-shared.7z";
    $tempDir = sys_get_temp_dir() . '/ffmpeg_install';
    $tempFile = $tempDir . '/ffmpeg.7z';
    $extractDir = $tempDir . '/extracted';
    
    // 创建临时目录
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    if (!is_dir($extractDir)) {
        mkdir($extractDir, 0777, true);
    }
    
    echo "📥 正在下载FFmpeg...\n";
    // 使用PHP下载文件
    $fileContent = file_get_contents($downloadUrl);
    if ($fileContent === false) {
        echo "❌ 下载失败，请检查网络连接\n";
        exit(1);
    }
    
    file_put_contents($tempFile, $fileContent);
    echo "✅ 下载完成\n";
    
    // 检查是否有7-Zip
    echo "🔍 检查7-Zip...\n";
    exec("where 7z", $output, $return_var);
    if ($return_var !== 0) {
        echo "❌ 未找到7-Zip，请先安装7-Zip:\n";
        echo "   下载地址: https://www.7-zip.org/download.html\n";
        exit(1);
    }
    
    $sevenZipPath = $output[0];
    echo "✅ 找到7-Zip: {$sevenZipPath}\n";
    
    // 解压文件
    echo "📦 正在解压FFmpeg...\n";
    $command = "\"{$sevenZipPath}\" x -o\"{$extractDir}\" \"{$tempFile}\" -y";
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        echo "❌ 解压失败\n";
        exit(1);
    }
    
    echo "✅ 解压完成\n";
    
    // 查找FFmpeg可执行文件
    $ffmpegDir = glob($extractDir . '/ffmpeg-*-full_build-shared')[0];
    $ffmpegBin = $ffmpegDir . '/bin';
    
    if (!file_exists($ffmpegBin . '/ffmpeg.exe')) {
        echo "❌ 未找到FFmpeg可执行文件\n";
        exit(1);
    }
    
    echo "📁 FFmpeg路径: {$ffmpegBin}\n";
    
    // 添加到系统环境变量
    echo "🔧 正在添加到系统环境变量...\n";
    $oldPath = getenv('PATH');
    $newPath = $oldPath . ';' . $ffmpegBin;
    
    // 临时添加到当前进程的环境变量
    putenv("PATH={$newPath}");
    
    // 永久添加到系统环境变量（需要管理员权限）
    $command = "setx PATH \"{$newPath}\" /M";
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        echo "✅ 成功添加到系统环境变量\n";
        echo "💡 注意：需要重新打开命令提示符或浏览器才能生效\n";
    } else {
        echo "⚠️ 添加到系统环境变量失败（可能需要管理员权限）\n";
        echo "   请手动将 {$ffmpegBin} 添加到系统PATH中\n";
    }
    
    // 测试FFmpeg
    echo "🧪 测试FFmpeg...\n";
    exec("{$ffmpegBin}/ffmpeg.exe -version", $output, $return_var);
    if ($return_var === 0) {
        echo "✅ FFmpeg安装成功！\n";
        echo "   版本信息: " . $output[0] . "\n";
    } else {
        echo "⚠️ FFmpeg安装但测试失败\n";
        echo "   请手动测试: ffmpeg -version\n";
    }
}

/**
 * 在Linux上安装FFmpeg
 */
function installFFmpegLinux() {
    echo "正在Linux上安装FFmpeg...\n";
    
    // 检查是否已安装
    exec("which ffmpeg", $output, $return_var);
    if ($return_var === 0) {
        echo "✅ FFmpeg已安装在: {$output[0]}\n";
        return;
    }
    
    // 检测Linux发行版
    $distro = getLinuxDistro();
    echo "检测到发行版: {$distro}\n";
    
    switch ($distro) {
        case 'ubuntu':
        case 'debian':
            $command = "sudo apt-get update && sudo apt-get install -y ffmpeg";
            break;
            
        case 'centos':
        case 'rhel':
            $command = "sudo yum install -y epel-release && sudo yum install -y ffmpeg ffmpeg-devel";
            break;
            
        case 'fedora':
            $command = "sudo dnf install -y ffmpeg";
            break;
            
        case 'arch':
            $command = "sudo pacman -S --noconfirm ffmpeg";
            break;
            
        default:
            echo "❌ 不支持的Linux发行版\n";
            echo "请手动安装FFmpeg\n";
            exit(1);
    }
    
    echo "📦 正在安装FFmpeg...\n";
    echo "   执行命令: {$command}\n";
    echo "   （可能需要输入sudo密码）\n\n";
    
    passthru($command, $return_var);
    
    if ($return_var === 0) {
        echo "\n✅ FFmpeg安装成功！\n";
        // 测试FFmpeg
        exec("ffmpeg -version", $output);
        echo "   版本信息: " . $output[0] . "\n";
    } else {
        echo "\n❌ FFmpeg安装失败\n";
        echo "请尝试手动安装\n";
        exit(1);
    }
}

/**
 * 在macOS上安装FFmpeg
 */
function installFFmpegMacOS() {
    echo "正在macOS上安装FFmpeg...\n";
    
    // 检查是否已安装
    exec("which ffmpeg", $output, $return_var);
    if ($return_var === 0) {
        echo "✅ FFmpeg已安装在: {$output[0]}\n";
        return;
    }
    
    // 检查是否安装了Homebrew
    echo "🔍 检查Homebrew...\n";
    exec("which brew", $output, $return_var);
    if ($return_var !== 0) {
        echo "❌ 未找到Homebrew，请先安装:\n";
        echo "   /bin/bash -c \"$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\"\n";
        exit(1);
    }
    
    echo "✅ 找到Homebrew: {$output[0]}\n";
    
    // 安装FFmpeg
    echo "📦 正在安装FFmpeg...\n";
    $command = "brew install ffmpeg";
    passthru($command, $return_var);
    
    if ($return_var === 0) {
        echo "\n✅ FFmpeg安装成功！\n";
        // 测试FFmpeg
        exec("ffmpeg -version", $output);
        echo "   版本信息: " . $output[0] . "\n";
    } else {
        echo "\n❌ FFmpeg安装失败\n";
        echo "请尝试手动安装\n";
        exit(1);
    }
}

/**
 * 获取Linux发行版
 */
function getLinuxDistro() {
    if (file_exists('/etc/os-release')) {
        $osRelease = parse_ini_file('/etc/os-release');
        if (isset($osRelease['ID'])) {
            return strtolower($osRelease['ID']);
        }
    }
    
    // 尝试其他方法
    if (file_exists('/etc/debian_version')) {
        return 'debian';
    } elseif (file_exists('/etc/centos-release')) {
        return 'centos';
    } elseif (file_exists('/etc/fedora-release')) {
        return 'fedora';
    } elseif (file_exists('/etc/arch-release')) {
        return 'arch';
    }
    
    return 'unknown';
}
