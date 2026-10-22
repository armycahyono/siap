<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtUserGroup extends Model
{
    protected $connection = 'cbt';

    protected $table = 'users_groups';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'group_id',
    ];
}
