<?php

namespace Maxlcoder\LaravelZwdd;

use Illuminate\Support\Facades\Cache;

class Zwdd
{
    protected $client;

    protected $domain;

    protected $appKey;

    protected $appSecret;

    public function __construct()
    {
        $this->domain = config('zwdd.app_server');
        $this->appKey = config('zwdd.app_key');
        $this->appSecret = config('zwdd.app_secret');
        $this->client = new ZwddClient();
        $this->client->setDomain($this->domain);
        $this->client->setAccessKey($this->appKey);
        $this->client->setSecretKey($this->appSecret);
    }

    /**
     * 获取 access_token
     */
    public function accessToken()
    {
        $cacheKey = 'zwdd:access_token:' . md5($this->domain . '-' . $this->appKey);
        $cacheToken = Cache::get($cacheKey);
        if ($cacheToken) {
            return $cacheToken;
        }
        $api = '/gettoken.json';
        $this->client->setApiName($api);
        $this->client->addParameters([
            'appkey' => $this->appKey,
            'appsecret' => $this->appSecret,
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

    public function getUserInfo($code)
    {
        $api = '/rpc/oauth2/getuserinfo_bycode.json';
        $token = $this->accessToken();
        $this->client->setApiName($api);
        $this->client->addParameters([
            'access_token' => $token,
            'code' => $code,
        ]);
        $result = $this->client->epaasCurl('POST', 3);
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        if (!isset($result['content']['success']) || !$result['content']['success']) {
            return null;
        }
        return $result['content']['data'];
    }

    public function appUser($authCode)
    {
        $api = '/rpc/oauth2/dingtalk_app_user.json';
        $token = $this->accessToken();
        $this->client->setApiName($api);
        $this->client->addParameters([
            'access_token' => $token,
            'auth_code' => $authCode,
        ]);
        $result = $this->client->epaasCurl('POST', 3);
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        if (!isset($result['content']['success']) || !$result['content']['success']) {
            return null;
        }
        return $result['content']['data'];
    }

}