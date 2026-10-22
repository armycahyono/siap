<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtTahunPelajaran extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_tp';

    protected $primaryKey = 'id_tp';

    public $timestamps = false;

    protected $fillable = ['tahun', 'active'];
}
