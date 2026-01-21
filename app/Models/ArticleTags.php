<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleTags extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    public function articles()
    {
        return $this->belongsToMany(
            \App\Models\Articles::class,
            'article_map_tags',
            'tag_id',
            'article_id'
        );
    }
}
