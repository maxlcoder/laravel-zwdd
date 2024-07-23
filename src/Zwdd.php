<?php

namespace Maxlcoder\LaravelZwdd;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Zwdd
{
    protected $client;

    protected $sdkClient;

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
        $this->sdkClient = new ZwddSdkClient();
        $this->sdkClient->setDomain($this->domain);
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
        \Illuminate\Support\Facades\Log::info(json_encode($result));
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        if (!isset($result['content']['success']) || !$result['content']['success']) {
            return null;
        }
        return $result['content']['data'];
    }

    public function getEmployeeByCode($tenantId, $employeeCode)
    {

        $api = '/mozi/employee/getEmployeeByCode';
        $this->client->setApiName($api);
        $this->client->setAccessKey($this->appKey);
        $this->client->setSecretKey($this->appSecret);
        $this->client->addParameters([
            'employeeCode' => $employeeCode,
            'tenantId' => $tenantId,
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

    /**
     * 通讯目录范围
     * 1. 根据通讯目录范围获取顶层组织 code
     * 2. 根据组织 code 再层层获取组织 code 列表
     */
    public function scopes($tenantId)
    {

        $api = '/auth/scopesV2';
        $this->client->setApiName($api);
        $this->client->setAccessKey($this->appKey);
        $this->client->setSecretKey($this->appSecret);
        $this->client->addParameters([
            'tenantId' => $tenantId,
        ]);
        $result = $this->client->epaasCurl('GET', 3);
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        if (!isset($result['content']['deptVisibleScopes']) || empty($result['content']['deptVisibleScopes'])) {
            return null;
        }
        return $result['content']['deptVisibleScopes'];
    }

    /**
     * 获取下一层组织 code 列表
     */
    public function pageSubOrganizationCodes($tenantId, $organizationCode)
    {
        $api = '/mozi/organization/pageSubOrganizationCodes';
        $this->client->setApiName($api);
        $this->client->setAccessKey($this->appKey);
        $this->client->setSecretKey($this->appSecret);
        $this->client->addParameters([
            'organizationCode' => $organizationCode,
            'tenantId' => $tenantId,
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


    /**
     * @param $tenantId
     * @param $organizationCode
     * @return mixed|null
     * @throws \Exception
     */
    public function listOrganizationsByCodes($tenantId, $organizationCodes)
    {
        $api = '/mozi/organization/listOrganizationsByCodes';
        $this->sdkClient->setApiName($api);
        $this->sdkClient->setAccessKey($this->appKey);
        $this->sdkClient->setSecretKey($this->appSecret);

        foreach ($organizationCodes as $organizationCode) {
            $this->sdkClient->addParameter("organizationCodes", $organizationCode);
        }
        $this->sdkClient->addParameter("tenantId", $tenantId);
        $result = $this->sdkClient->epaasCurlPost(3);
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        return $result['data'];
    }

    public function getOrganizationByCode($tenantId, $organizationCode)
    {
        $api = '/mozi/organization/getOrganizationByCode';
        $this->sdkClient->setApiName($api);
        $this->sdkClient->setAccessKey($this->appKey);
        $this->sdkClient->setSecretKey($this->appSecret);
        $this->sdkClient->addParameter("organizationCode", $organizationCode);
        $this->sdkClient->addParameter("tenantId", $tenantId);
        $result = $this->sdkClient->epaasCurlPost(3);
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        if (!isset($result['content']['success']) || !$result['content']['success']) {
            return null;
        }

        return $result['content']['data'];
    }


    public function pageOrganizationEmployeeCodes($tenantId, $organizationCode)
    {

        $api = '/mozi/organization/pageOrganizationEmployeeCodes';
//        $this->sdkClient->setApiName($api);
//        $this->sdkClient->setAccessKey($this->appKey);
//        $this->sdkClient->setSecretKey($this->appSecret);
//        $this->sdkClient->addParameter("tenantId", $tenantId);
//        $this->sdkClient->addParameter("organizationCode", $organizationCode);
//        $this->client->addParameters([
//            'organizationCodes' => '["GO_d39f236981c346b2b44950c930884b56","GO_c1146cfcca3e41e2be2187aa0749ff8f"]',
//            'tenantId' => $tenantId,
//        ]);
//        $result = $this->sdkClient->epaasCurlPost(3);
        $this->client->setApiName($api);
        $this->client->setAccessKey($this->appKey);
        $this->client->setSecretKey($this->appSecret);
        $this->client->addParameters([
            'organizationCode' => $organizationCode,
            'tenantId' => $tenantId,
        ]);
        $result = $this->client->epaasCurl('POST', 3);
        dd($result);
        if (!isset($result['success']) || !$result['success']) {
            return null;
        }
        if (!isset($result['content']['success']) || !$result['content']['success']) {
            return null;
        }
        return $result['content']['data'];
    }

}