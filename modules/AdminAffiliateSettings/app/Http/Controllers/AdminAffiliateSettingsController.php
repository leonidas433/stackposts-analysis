<?php

namespace Modules\AdminAffiliateSettings\Http\Controllers;

use App\Http\Controllers\Controller;

class AdminAffiliateSettingsController extends Controller
{
    public function index()
    {
        return view('adminaffiliatesettings::index');
    }
}
