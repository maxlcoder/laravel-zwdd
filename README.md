## 浙政钉-专有钉钉

### 说明

本扩展包转为浙政钉专有钉钉接入使用，目前主要基层浙政钉登录（包含扫码登录）

### 配置
`config/zwdd.php` 配置配置如下，其中区分专有钉钉单点登录和扫码登录的 key 和 secret ，一般情况这两个也都是一样的。开发者可以自由选择。

```php
<?php

return [
    'app_server' => env('ZWDD_APP_SERVER', ''),
    'app_key' => env('ZWDD_APP_KEY', ''),
    'app_secret' => env('ZWDD_APP_SECRET', ''),
    'scan_app_key' => env('ZWDD_SCAN_APP_KEY', env('ZWDD_APP_KEY', '')),
    'scan_app_secret' => env('ZWDD_SCAN_APP_SECRET', env('ZWDD_APP_SECRET', '')),
];

```

### 集成接口

当前集成接口如下，详细接口说明可以参考 [专有钉钉](https://open-portal.on-premises.dingtalk.com/portal/#/helpdoc?apiType=serverapi&docKey=2674834)

* `/gettoken.json` 获取 access_token ，设置有缓存，避免重复调用
* `/rpc/oauth2/getuserinfo_bycode.json` 扫码登录，通过 code 获取用户信息
* `/rpc/oauth2/dingtalk_app_user.json` 应用内免登（授权登录），通过 auth_code 获取用户信息
* `/mozi/employee/getEmployeeByCode` 根据员工 code 获取员工信息
* `/auth/scopesV2` 获取通讯录范围