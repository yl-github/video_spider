<?php

namespace App\Parsers;

use App\Utils\UserAgent;

/**
 * 微视视频解析器
 */
class WeishiParser extends BaseParser
{
    protected static function getHeaders()
    {
        return [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36'
        ];
    }

    public static function parse($url)
    {
        if (strpos($url, 'video.weishi.qq.com')) {
            $url = Common::getLocation($url);
        }

        preg_match('/feed\/(.*)\b/', $url, $id);

        if (!$id) {
            preg_match('/id=(.*)/', $url, $id);
        }

        if (empty($id[1])) {
            return self::error(400, '无法解析视频 ID');
        }

        $arr = Common::getCurl("https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage?feedid={$id[1]}", null, self::getHeaders());
        $data = json_decode($arr, true);

        if (!$data) {
            return self::error(500, '视频数据解析失败');
        }

        $item = $data['data']['feeds'][0];
        $videoUrl = $item['video_url'];
        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }

        if ($videoUrl) {
            return self::success([
                'title' => $item['feed_desc_withat'],
                'author' => $item['poster']['nick'],
                'avatar' => $item['poster']['avatar'],
                'time' => $item['poster']['createtime'],
                'cover' => $item['images'][0]['url'],
                'url' => $video_url
            ]);
        }
        return self::error(201, '未找到视频URL');
    }
}