<?php

namespace Modules\AppPublishingCampaigns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\AppPublishingCampaigns\Database\Factories\PostCampaignFactory;

class PostCampaign extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];
}
