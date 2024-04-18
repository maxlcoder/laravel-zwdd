<?php

namespace Maxlcoder\LaravelZwdd\Facades;

use Illuminate\Support\Facades\Facade;

class Zwdd extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'zwdd';
    }
}