<?php

namespace Modules\AppFiles\Facades;

use Illuminate\Support\Facades\Facade;

class UploadFile extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Modules\AppFiles\Services\UploadFileService::class;
    }
}
