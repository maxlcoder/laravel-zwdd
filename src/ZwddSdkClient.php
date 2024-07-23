<?php

namespace Maxlcoder\LaravelZwdd;


class ZwddSdkClient
{
    var $configs = null;
    var $headers = null;
    var $timestamp = null;

    public function __construct()
    {  #初始化方法
        $this->configs['epaas'] = array(
            'api_version' => '1.0',
            'api_timeout' => 3// 超时时间，单位秒
        );
        $date = date_create();
        $timestamp = date_timestamp_get($date);
        // 注意请求epaas必须加8个小时，否则与epaas时间匹配不上
        $timestamp += 150;
        $this->timestamp = $timestamp;
    }

    function configs()
    {
        return $this->configs;
    }

    function setDomain($domain)
    {
        $this->configs['epaas']['api_server'] = $domain;
    }

    function setAccessKey($accessKey)
    {
        $this->configs['epaas']['api_key'] = $accessKey;
    }

    function setSecretKey($secretKey)
    {
        $this->configs['epaas']['api_secret'] = $secretKey;
    }

    function setApiName($apiName)
    {
        $this->configs['epaas']['api_name'] = $apiName;
    }

    function addParameter($key, $value)
    {
        $this->configs['epaas']['params'][$key][] = $value;
        //print_r($this->configs['epaas']);
    }

    function epaasNicInfo()
    {
        $cmd = '/sbin/ifconfig eth0|/usr/bin/head -2';
        $output = `$cmd`;
        if (!$output) {
            return false;
        }
        $lines = explode("\n", $output);
        $ret = array();
        foreach ($lines as $line) {
            $tmp = array();
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

    function epaasSignature($method, $timestamp, $nonce, $uri, $params)
    {
        $init = $this->configs();
        $bytes = sprintf("%s\n%s\n%s\n%s", $method, $timestamp, $nonce, $uri);
        if (!empty($params)) {
            $bytes = sprintf("%s\n%s\n%s\n%s\n%s", $method, $timestamp, $nonce, $uri, $params);
        }
        #print_r($bytes);
        $hash = hash_hmac('sha256', $bytes, $init['epaas']['api_secret'], true);
        return base64_encode($hash);
    }

    function epaasHeaders($method)
    {
        $timestamp = $this->timestamp;
        $init = $this->configs();
        $params = $init['epaas']['params'];
        $api = $init['epaas']['api_name'];

        //这里ip和mac写的是假的，用户调用时改为自己的ip和mac
        $addr = array(
            'ip' => '127.0.0.1',
            'mac' => ''
        );//$this->epaasNicInfo();
        if (!$addr) {
            return false;
        }

        $formatTime = strftime('%Y-%m-%dT%H:%M:%S.000+08:00', $timestamp);
        $nonce = sprintf('%d000%d', $timestamp, rand(1000, 9999));
        if (!empty($params)) {
            ksort($params, SORT_STRING);
        }
        $ret = array();
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                foreach ($v as $value) {
                    $ret[] = sprintf('%s=%s', $k, $value);
                }
            }
        }
        sort($ret);
        $sig = $this->epaasSignature($method, $formatTime, $nonce, $api, implode('&', $ret));
        $this->headers = array(
            'X-Hmac-Auth-Timestamp' => $formatTime,
            'X-Hmac-Auth-Version' => $init['epaas']['api_version'],
            'X-Hmac-Auth-Nonce' => $nonce,
            'apiKey' => $init['epaas']['api_key'],
            'X-Hmac-Auth-Signature' => $sig,
            'X-Hmac-Auth-IP' => $addr['ip'],
            'X-Hmac-Auth-MAC' => $addr['mac']
        );
        return $this->headers;
    }


    function epaasCurlGet($timeout = 1, $onlyReturnContent = true)
    {
        $headerAry = $this->epaasHeaders('GET');
        $init = $this->configs();
        $params = $init['epaas']['params'];
        $api = $init['epaas']['api_name'];
        $url = sprintf('%s%s', $init['epaas']['api_server'], $api);
        if (!empty($params)) {
            ksort($params, SORT_STRING);
        }
        $ret = array();
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                foreach ($v as $value) {
                    $ret[] = sprintf('%s=%s', $k, $value);
                }
            }
        }

        $getparam = implode('&', $ret);
        $url = sprintf('%s?%s', $url, $getparam);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($headerAry) {
            $tmp = array();
            foreach ($headerAry as $k => $v) {
                $tmp[] = sprintf('%s: %s', $k, $v);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $tmp);
        }
        $data = curl_exec($ch);
        $error = curl_error($ch);
        if ($error) {
            $msg = "epaasCurlPost|curl error: ".$error."|".$url."|";
            error_log($msg);
        }
        curl_close($ch);
        $ret = json_decode($data, true);
        if (!$ret['success']) {
            $msg = "epaasCurlPost|result not success: ".$data."|".$url."|";
            error_log($msg);
        }

        if ($onlyReturnContent) {
            return $ret['content'];
        } else {
            return $ret;
        }
    }

    /**
     * 以POST方式请求epaas
     * @param  int  $timeout
     * @param  bool  $onlyReturnContent  是否只返回结果中的content
     * @return mixed
     */
    function epaasCurlPost($timeout = 1, $onlyReturnContent = true)
    {
        $headerAry = $this->epaasHeaders('POST');
        $init = $this->configs();
        $params = $init['epaas']['params'];
        $api = $init['epaas']['api_name'];
        $url = sprintf('%s%s', $init['epaas']['api_server'], $api);
        $ch = curl_init();
        $ret = array();
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                foreach ($v as $value) {
                    $ret[] = sprintf('%s=%s', $k, $value);
                }
            }
        }

        $getparam = implode('&', $ret);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $getparam);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($headerAry) {
            $tmp = array();
            foreach ($headerAry as $k => $v) {
                $tmp[] = sprintf('%s: %s', $k, $v);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $tmp);
        }
        $data = curl_exec($ch);
        $error = curl_error($ch);
        if ($error) {
            $msg = "epaasCurlPost|curl error: ".$error."|".$url."|";
            error_log($msg);
        }
        curl_close($ch);
        $ret = json_decode($data, true);
        if (!$ret['success']) {
            $msg = "epaasCurlPost|result not success: ".$data."|".$url."|";
            error_log($msg);
        }

        if ($onlyReturnContent) {
            return $ret['content'];
        } else {
            return $ret;
        }
    }
}