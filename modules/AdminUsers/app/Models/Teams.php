<?php

namespace Modules\AdminUsers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Teams extends Model
{
    public $timestamps = false;

    protected $table = 'teams';

    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'data' => 'array',
    ];

    /**
     * Get all members of this team.
     */
    /*public function members()
    {
        return $this->hasMany(TeamMembers::class, 'team_id');
    }*/

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'uid')
            ->withPivot('status', 'permissions', 'pending');
    }

    /**
     * Get the user who owns the team.
     */
    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner', 'id');
    }
}
