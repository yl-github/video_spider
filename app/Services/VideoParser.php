<?php

namespace App\Services;

/**
 * 视频解析服务
 */
class VideoParser
{
    private const PLATFORMS = [
        'pipixia' => [
            'class' => \App\Parsers\PipixiaParser::class,
            'domains' => ['pipix.com']
        ],
        'douyin' => [
            'class' => \App\Parsers\DouyinParser::class,
            'domains' => ['douyin.com']
        ],
        'weibo' => [
            'class' => \App\Parsers\WeiboParser::class,
            'domains' => ['weibo.com']
        ],
        'izuiyou' => [
            'class' => \App\Parsers\IzuiyouParser::class,
            'domains' => ['izuiyou.com']
        ],
        'pipigx' => [
            'class' => \App\Parsers\PipigxParser::class,
            'domains' => ['ippzone.com', 'pipigx.com']
        ]
    ];

    /**
     * 解析视频 URL
     * 
     * @param string $url 视频 URL
     * @return array 解析结果数据数组
     * @throws \InvalidArgumentException 当平台不支持或参数错误时
     * @throws \RuntimeException 解析失败时
     */
    public function parse(string $url): array
    {
        $platform = $this->getPlatform($url);
        if (!$platform) {
            throw new \InvalidArgumentException('不支持的视频平台');
        }
        
        return self::PLATFORMS[$platform]['class']::parse($url);
    }

    /**
     * 根据 URL 获取平台标识
     *
     * @param string $url 视频 URL
     * @return string|null 平台标识，如果不支持则返回 null
     */
    private function getPlatform(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $host));

        foreach (self::PLATFORMS as $platform => $config) {
            foreach ($config['domains'] as $domain) {
                if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                    return $platform;
                }
            }
        }

        return null;
    }

    /**
     * 获取所有支持的平台列表
     * 
     * @return array 支持的平台标识数组
     */
    public function getSupportedPlatforms(): array
    {
        return array_keys(self::PLATFORMS);
    }
}
