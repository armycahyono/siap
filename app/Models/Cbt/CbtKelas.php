<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtKelas extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_kelas';

    protected $primaryKey = 'id_kelas';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'nama_kelas',
        'kode_kelas',
        'jurusan_id',
        'level_id',
        'guru_id',
        'siswa_id',
        'jumlah_siswa',
        'set_siswa',
    ];
}
