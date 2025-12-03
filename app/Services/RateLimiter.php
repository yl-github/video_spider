<?php

namespace App\Services;

use App\Utils\Config;
use App\Utils\Logger;

/**
 * API 速率限制服务
 *
 * 基于 IP 地址实现简单的速率限制
 * 使用文件系统存储请求计数
 */
class RateLimiter
{
    
    /**
     * 存储目录
     */
    private const STORAGE_DIR = __DIR__ . '/../../storage/rate_limit/';
    
    /**
     * 检查是否超过速率限制
     *
     * @param string $ip 客户端 IP 地址
     * @param int|null $limit 每分钟最大请求数，null 则使用配置值
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function check(string $ip, ?int $limit = null): array
    {
        $limit = $limit ?? Config::get('rate_limit.max_requests', 60);
        $timeWindow = Config::get('rate_limit.time_window', 60);
        
        // 确保存储目录存在
        if (!is_dir(self::STORAGE_DIR)) {
            mkdir(self::STORAGE_DIR, 0755, true);
        }
        
        $file = self::STORAGE_DIR . md5($ip) . '.json';
        $now = time();
        
        // 使用文件锁定读取现有数据
        $fp = fopen($file, 'c+');
        if (!$fp) {
            // 如果无法打开文件，记录错误但允许请求通过
            Logger::error('无法打开速率限制文件', ['file' => $file, 'ip' => $ip]);
            return [
                'allowed' => true,
                'remaining' => $limit,
                'reset_time' => $now + $timeWindow
            ];
        }
        
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            Logger::error('无法锁定速率限制文件', ['file' => $file, 'ip' => $ip]);
            return [
                'allowed' => true,
                'remaining' => $limit,
                'reset_time' => $now + $timeWindow
            ];
        }
        
        // 读取现有数据
        $data = [];
        $fileSize = filesize($file);
        if ($fileSize !== false && $fileSize > 0) {
            $content = fread($fp, $fileSize);
            $data = json_decode($content, true) ?: [];
        }
        
        // 清理过期数据
        if (isset($data['reset_time']) && $data['reset_time'] < $now) {
            $data = [];
        }
        
        // 初始化或更新计数
        if (empty($data)) {
            $data = [
                'count' => 0,
                'reset_time' => $now + $timeWindow
            ];
        }
        
        // 检查是否超过限制
        $allowed = $data['count'] < $limit;
        $remaining = max(0, $limit - $data['count']);
        
        if (!$allowed) {
            // 记录速率限制触发
            Logger::error('速率限制触发', [
                'ip' => $ip,
                'count' => $data['count'],
                'limit' => $limit,
                'reset_time' => $data['reset_time']
            ]);
        }
        
        if ($allowed) {
            $data['count']++;
        }
        
        // 保存数据
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_time' => $data['reset_time']
        ];
    }
    
    /**
     * 获取客户端 IP 地址
     * 
     * @return string IP 地址
     */
    public static function getClientIp(): string
    {
        // 检查代理
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * 清理过期的限制记录
     * 
     * @return int 清理的文件数量
     */
    public static function cleanup(): int
    {
        if (!is_dir(self::STORAGE_DIR)) {
            return 0;
        }
        
        $files = glob(self::STORAGE_DIR . '*.json');
        if ($files === false) {
            return 0;
        }
        
        $now = time();
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = json_decode($content, true);
            if ($data && isset($data['reset_time']) && $data['reset_time'] < $now) {
                @unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}

