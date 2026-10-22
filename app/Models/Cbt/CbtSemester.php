<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtSemester extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_smt';

    protected $primaryKey = 'id_smt';

    public $timestamps = false;

    protected $fillable = ['smt', 'nama_smt', 'active'];
}
