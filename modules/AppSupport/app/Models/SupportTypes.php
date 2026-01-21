<?php

namespace Modules\AppSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\AppSupport\Database\Factories\SupportTypesFactory;

class SupportTypes extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): SupportTypesFactory
    // {
    //     // return SupportTypesFactory::new();
    // }
}
