<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtBankSoal extends Model
{
    protected $connection = 'cbt';

    protected $table = 'cbt_bank_soal';

    protected $primaryKey = 'id_bank';

    public $timestamps = false;

    protected $fillable = [
        'bank_jenis_id',
        'bank_kode',
        'bank_level',
        'bank_kelas',
        'bank_mapel_id',
        'bank_jurusan_id',
        'bank_guru_id',
        'bank_nama',
        'kkm',
        'jml_soal',
        'jml_esai',
        'tampil_pg',
        'tampil_esai',
        'bobot_pg',
        'bobot_esai',
        'opsi',
        'date',
        'status',
        'soal_agama',
        'id_tp',
        'id_smt',
        'deskripsi',
        'jml_kompleks',
        'tampil_kompleks',
        'bobot_kompleks',
        'jml_jodohkan',
        'tampil_jodohkan',
        'bobot_jodohkan',
        'jml_isian',
        'tampil_isian',
        'bobot_isian',
        'status_soal',
    ];
}
