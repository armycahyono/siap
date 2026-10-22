<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtUser extends Model
{
    protected $connection = 'cbt';

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'username',
        'password',
        'email',
        'activation_selector',
        'activation_code',
        'forgotten_password_selector',
        'forgotten_password_code',
        'forgotten_password_time',
        'remember_selector',
        'remember_code',
        'created_on',
        'last_login',
        'active',
        'first_name',
        'last_name',
        'company',
        'phone',
    ];
}
