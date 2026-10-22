<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtJadwal extends Model
{
    protected $connection = 'cbt';

    protected $table = 'cbt_jadwal';

    protected $primaryKey = 'id_jadwal';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_bank',
        'id_jenis',
        'tgl_mulai',
        'tgl_selesai',
        'durasi_ujian',
        'pengawas',
        'acak_soal',
        'acak_opsi',
        'hasil_tampil',
        'token',
        'status',
        'ulang',
        'reset_login',
        'rekap',
        'jam_ke',
        'jarak',
        'time_create',
    ];
}
