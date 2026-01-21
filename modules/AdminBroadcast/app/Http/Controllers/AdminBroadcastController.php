<?php

namespace Modules\AdminBroadcast\Http\Controllers;

use App\Http\Controllers\Controller;

class AdminBroadcastController extends Controller
{
    public function settings()
    {
        return view('adminbroadcast::settings');
    }
}
