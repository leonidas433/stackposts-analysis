<?php

namespace Modules\AdminCaptcha\Http\Controllers;

use App\Http\Controllers\Controller;

class AdminCaptchaController extends Controller
{
    public function index()
    {
        return view('admincaptcha::index');
    }
}
