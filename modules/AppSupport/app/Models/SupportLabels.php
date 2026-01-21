<?php

namespace Modules\AppSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\AppSupport\Database\Factories\SupportLabelsFactory;

class SupportLabels extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): SupportLabelsFactory
    // {
    //     // return SupportLabelsFactory::new();
    // }
}
