<?php

namespace Maxlcoder\LaravelZwdd;
use Illuminate\Support\ServiceProvider;

class ZwddServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 发布配置
        $this->publishes([__DIR__.'/config/zwdd.php' => config_path('zwdd.php')], 'config');
    }

}