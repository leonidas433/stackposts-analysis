<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
print_r(Modules\AdminLanguages\Models\Languages::where('status', 1)->pluck('code')->toArray());
