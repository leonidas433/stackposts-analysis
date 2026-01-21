<?php

namespace Modules\AdminAPIIntegration\Http\Controllers;

use App\Http\Controllers\Controller;

class AdminAPIIntegrationController extends Controller
{
    public function index()
    {
        return view('adminapiintegration::index');
    }
}
