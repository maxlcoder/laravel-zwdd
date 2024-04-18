<?php

namespace Maxlcoder\LaravelZwdd;

use Illuminate\Support\Facades\Cache;

class Zwdd
{
    protected $client;

    public function __construct()
    {
        $this->client = new ZwddClient();
    }

    /**
     * 获取 access_token
     */
    public function accessToken()
    {
        $cacheKey = 'zwdd:access_token:' . md5(config('zwdd.app_server') . '-' . config('zwdd.app_key'));
        $cacheToken = Cache::get($cacheKey);
        if ($cacheToken) {
            return $cacheToken;
        }
        $api = '/gettoken.json';
        $this->client->setApiName($api);
        $this->client->addParameters([
            'appkey' => config('zwdd.app_key'),
            'appsecret' => config('zwdd.app_secret'),
        ]);
        $result = $this->client->epaasCurl('GET', 3);
        if (!isset($result['success']) || !$result['success']) {
            return '';
        }
        $token = $result['content']['data']['accessToken'];
        $ttl = $result['content']['data']['expiresIn'] - 600; // 提前十分钟过期
        Cache::put($cacheKey, $token, $ttl);
        return $token;
    }

}