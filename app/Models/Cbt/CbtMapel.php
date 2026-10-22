<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtMapel extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_mapel';

    protected $primaryKey = 'id_mapel';

    public $timestamps = false;

    protected $fillable = [
        'nama_mapel',
        'kode',
        'kelompok',
        'bobot_p',
        'bobot_k',
        'jenjang',
        'urutan',
        'status',
        'deletable',
        'urutan_tampil',
    ];
}
