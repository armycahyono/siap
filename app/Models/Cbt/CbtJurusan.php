<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtJurusan extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_jurusan';

    protected $primaryKey = 'id_jurusan';

    public $timestamps = false;

    protected $fillable = [
        'nama_jurusan',
        'kode_jurusan',
        'mapel_peminatan',
        'status',
        'deletable',
    ];
}
