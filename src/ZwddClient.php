<?php

namespace Maxlcoder\LaravelZwdd;

use Illuminate\Support\Facades\Http;

class ZwddClient
{
    protected $configs;
    protected $headers;
    protected $timestamp;


    public function __construct()
    {
        $this->configs['epaas'] = array(
            'api_version' => '1.0',
            'api_timeout' => 3// 瓒呮椂鏃堕棿锛屽崟浣嶇
        );
        $this->timestamp = time();
    }

    public function configs(){
        return $this->configs;
    }

    public function setDomain($domain)
    {
        $this->configs['epaas']['api_server']=$domain;
    }

    public function setAccessKey($accessKey){
        $this->configs['epaas']['api_key'] = $accessKey;
    }

    public function setSecretKey($secretKey){
        $this->configs['epaas']['api_secret'] = $secretKey;
    }

    public function setApiName($apiName)
    {
        $this->configs['epaas']['api_name'] = $apiName;
    }

    public function addParameters($params)
    {
        $this->configs['epaas']['params'] = $params;
    }

    public function epaasNicInfo()
    {
        $cmd = '/sbin/ifconfig eth0|/usr/bin/head -2';
        $output = `$cmd`;
        if (!$output) {
            return false;
        }
        $lines = explode("\n", $output);
        $ret = [];
        foreach ($lines as $line) {
            $tmp = [];
            if (preg_match('/HWaddr ((?:[0-9A-Fa-f]{2}:)+[0-9A-Fa-f]{2})/', $line, $tmp)) {
                $ret['mac'] = $tmp[1];
                continue;
            }
            if (preg_match('/inet addr:((?:[0-9]{1,3}\.)+[0-9]{1,3})/', $line, $tmp)) {
                $ret['ip'] = $tmp[1];
                continue;
            }
        }
        return $ret;
    }

    public function epaasSignature($method, $timestamp, $nonce, $uri, $params)
    {
        $init = $this->configs();
        $bytes = sprintf("%s\n%s\n%s\n%s", $method, $timestamp, $nonce, $uri);
        if (!empty($params)) {
            $bytes = sprintf("%s\n%s\n%s\n%s\n%s", $method, $timestamp, $nonce, $uri, $params);
        }
        $hash = hash_hmac('sha256', $bytes, $init['epaas']['api_secret'], true);
        return base64_encode($hash);
    }

    public function epaasHeaders($method)
    {
        $timestamp = $this->timestamp;
        $init = $this->configs();
        $params = $init['epaas']['params'];
        $api = $init['epaas']['api_name'];
        //这里ip和mac写的是假的，用户调用时改为自己的ip和mac
        $addr = [
            'ip' => '127.0.0.1',
            'mac' => '',
        ];//$this->epaasNicInfo();
        if (!$addr) {
            return false;
        }

        $formatTime = strftime('%Y-%m-%dT%H:%M:%S.000+08:00', $timestamp);
        $nonce = sprintf('%d000%d', $timestamp, rand(1000, 9999));
        if (!empty($params)) {
            ksort($params, SORT_STRING);
        }
        $paramsString = http_build_query($params);
        $sig = $this->epaasSignature($method, $formatTime, $nonce, $api, $paramsString);
        $this->headers = [
            'X-Hmac-Auth-Timestamp' => $formatTime,
            'X-Hmac-Auth-Version' => $init['epaas']['api_version'],
            'X-Hmac-Auth-Nonce' => $nonce,
            'apiKey' => $init['epaas']['api_key'],
            'X-Hmac-Auth-Signature' => $sig,
            'X-Hmac-Auth-IP' => $addr['ip'],
            'X-Hmac-Auth-MAC' => $addr['mac'],
        ];
        return $this->headers;
    }

    function epaasCurl($method = 'GET', $timeout = 1, $onlyReturnContent = true)
    {
        $headerAry = $this->epaasHeaders($method);
        $init = $this->configs();
        $params = $init['epaas']['params'];
        $api = $init['epaas']['api_name'];
        $url = sprintf('%s%s', $init['epaas']['api_server'], $api);
        if (!empty($params)) {
            ksort($params, SORT_STRING);
        }
        if ($method == 'GET') {
            $response = Http::withoutVerifying()->withHeaders($headerAry)->get($url, $params);
        } elseif ($method = 'POST') {
            $response = Http::withoutVerifying()->withHeaders($headerAry)->asForm()->post($url, $params);
        }
        if (!$response->ok()) {
            throw new \Exception('请求失败');
        }
        return $response->json();
    }
}