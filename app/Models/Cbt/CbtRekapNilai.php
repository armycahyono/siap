<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtRekapNilai extends Model
{
    protected $connection = 'cbt';

    protected $table = 'cbt_rekap_nilai';

    protected $primaryKey = 'id_rekap_nilai';

    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'id_tp',
        'tp',
        'id_smt',
        'smt',
        'id_jenis',
        'kode_jenis',
        'id_bank',
        'id_mapel',
        'id_siswa',
        'id_kelas',
        'kelas',
        'mulai',
        'selesai',
        'durasi',
        'bobot_pg',
        'jawaban_pg',
        'nilai_pg',
        'bobot_esai',
        'jawaban_esai',
        'nilai_esai',
        'id_guru',
        'nama_siswa',
        'no_peserta',
        'soal_kompleks',
        'soal_jodohkan',
        'soal_isian',
        'soal_essai',
        'time_create',
    ];
}
