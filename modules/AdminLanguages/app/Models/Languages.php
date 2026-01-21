<?php

namespace Modules\AdminLanguages\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Languages extends Model
{
    use HasFactory;

    protected $table = 'languages';

    public $timestamps = false;

    protected $guarded = [];
}
