<?php

namespace Maxlcoder\LaravelZwdd;

use Illuminate\Support\Facades\Cache;

class Zwdd
{
    protected $client;

    protected $domain;

    protected $appKey;
    protected $appSecret;

    protected $scanAppKey;
    protected $scanAppSecret;

    public function __construct()
    {
        $this->domain = config('zwdd.app_server');
        $this->appKey = config('zwdd.app_key');
        $this->appSecret = config('zwdd.app_secret');
        $this->scanAppKey = config('zwdd.scan_app_key');
        $this->scanAppSecret = config('zwdd.scan_app_secret');
        $this->client = new ZwddClient();
        $this->client->setDomain($this->domain);
    }

    /**
     * 获取 access_token
     */
    public function accessToken()
    {
        $this->client->setAccessKey($this->appKey);
        $this->client->setSecretKey($this->appSecret);
        return $this->getAccessToken($this->appKey, $this->appSecret);
    }

    public function scanAccessToken()
    {
        $this->client->setAccessKey($this->scanAppKey);
        $this->client->setSecretKey($this->scanAppSecret);
        return $this->getAccessToken($this->scanAppKey, $this->scanAppSecret);
    }

    private function getAccessToken($appKey, $appSecret)
    {
        $cacheKey = 'zwdd:access_token:' . md5($this->domain . '-' . $appKey);
        $cacheToken = Cache::get($cacheKey);
        if ($cacheToken) {
            return $cacheToken;
        }
        $api = '/gettoken.json';
        $this->client->setApiName($api);
        $this->client->addParameters([
            'appkey' => $appKey,
            'appsecret' => $appSecret,
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

    public function getScanUserInfo($code)
    {
        $api = '/rpc/oauth2/getuserinfo_bycode.json';
        $token = $this->scanAccessToken();
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

    public function getAppUser($authCode)
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