<?php

namespace Modules\AdminLanguages\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\AdminLanguages\Database\Factories\LanguageItemsFactory;

class LanguageItems extends Model
{
    use HasFactory;

    protected $table = 'language_items';

    public $timestamps = false;

    protected $guarded = [];
}
