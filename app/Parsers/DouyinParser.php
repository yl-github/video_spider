<?php

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 抖音视频解析器
 */
class DouyinParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return ['User-Agent' => UserAgent::mobile()];
    }

    public static function parse(string $url): array
    {
        $loc = HttpClient::getLocation($url);
        if (!$loc || !preg_match('/\d+/', $loc, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $result = self::fetch("https://www.iesdouyin.com/share/video/{$match[0]}");
        if (!$result || !preg_match('/window\._ROUTER_DATA\s*=\s*(.*?)\<\/script>/s', $result['data'], $matches)) {
            throw new \RuntimeException('解析视频信息失败');
        }

        $data = self::parseJson(trim($matches[1]));
        
        $item = $data['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0] ?? null;
        if (!$item || !isset($item['video']['play_addr']['uri'])) {
            throw new \RuntimeException('视频数据解析失败');
        }

        $videoId = $item['video']['play_addr']['uri'];
        if (!$videoId) {
            throw new \RuntimeException('未找到视频 URL');
        }
        
        return [
            'author' => $item['author']['nickname'] ?? '',
            'uid' => $item['author']['unique_id'] ?? '',
            'avatar' => $item['author']['avatar_medium']['url_list'][0] ?? '',
            'like' => $item['statistics']['digg_count'] ?? 0,
            'time' => $item['create_time'] ?? 0,
            'title' => $item['desc'] ?? '',
            'cover' => $item['video']['cover']['url_list'][0] ?? '',
            'url' => "http://www.iesdouyin.com/aweme/v1/play/?video_id={$videoId}&ratio=1080p&line=0",
            'music' => [
                'author' => $item['music']['author'] ?? '',
                'avatar' => $item['music']['cover_large']['url_list'][0] ?? ''
            ]
        ];
    }
}
