<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtKelasSiswa extends Model
{
    protected $connection = 'cbt';

    protected $table = 'kelas_siswa';

    protected $primaryKey = 'id_kelas_siswa';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_kelas_siswa',
        'id_tp',
        'id_smt',
        'id_siswa',
        'id_kelas',
    ];
}
