<?php

namespace Modules\AdminURLShorteners\Http\Controllers;

use App\Http\Controllers\Controller;

class AdminURLShortenersController extends Controller
{
    public function index()
    {
        return view('adminurlshorteners::index');
    }
}
