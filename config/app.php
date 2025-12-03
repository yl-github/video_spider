<?php

use App\Utils\Config;

// 确保环境变量已加载
Config::env('APP_NAME');

/**
 * 应用配置文件
 */
return [
    // API 速率限制配置
    'rate_limit' => [
        'enabled' => Config::env('RATE_LIMIT_ENABLED', 'true') !== 'false',
        'max_requests' => (int)Config::env('RATE_LIMIT_MAX_REQUESTS', 60),
        'time_window' => (int)Config::env('RATE_LIMIT_TIME_WINDOW', 60),
    ],

    // HTTP 客户端配置
    'curl' => [
        'connect_timeout' => (int)Config::env('CURL_CONNECT_TIMEOUT', 5),
        'timeout' => (int)Config::env('CURL_TIMEOUT', 10),
        'max_retries' => (int)Config::env('CURL_MAX_RETRIES', 3),
    ],
];

