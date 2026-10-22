<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtSiswa extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_siswa';

    protected $primaryKey = 'id_siswa';

    public $timestamps = false;

    protected $fillable = [
        'nisn',
        'nis',
        'nama',
        'jenis_kelamin',
        'username',
        'password',
        'kelas_awal',
        'tahun_masuk',
        'sekolah_asal',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'hp',
        'email',
        'foto',
        'anak_ke',
        'status_keluarga',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'nama_ayah',
        'tgl_lahir_ayah',
        'pendidikan_ayah',
        'pekerjaan_ayah',
        'nohp_ayah',
        'alamat_ayah',
        'nama_ibu',
        'tgl_lahir_ibu',
        'pendidikan_ibu',
        'pekerjaan_ibu',
        'nohp_ibu',
        'alamat_ibu',
        'nama_wali',
        'tgl_lahir_wali',
        'pendidikan_wali',
        'pekerjaan_wali',
        'nohp_wali',
        'alamat_wali',
        'nik',
        'warga_negara',
        'uid',
    ];
}
