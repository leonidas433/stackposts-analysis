<?php

namespace Modules\AdminAnalytee\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyteePrompt extends Model
{
    protected $table = 'analytee_prompts';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
