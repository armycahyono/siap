<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtGuru extends Model
{
    protected $connection = 'cbt';

    protected $table = 'master_guru';

    protected $primaryKey = 'id_guru';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nip',
        'nama_guru',
        'email',
        'kode_guru',
        'username',
        'password',
        'no_ktp',
        'tempat_lahir',
        'tgl_lahir',
        'jenis_kelamin',
        'agama',
        'no_hp',
        'alamat_jalan',
        'rt_rw',
        'dusun',
        'kelurahan',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'kewarganegaraan',
        'nuptk',
        'jenis_ptk',
        'tgs_tambahan',
        'status_pegawai',
        'status_aktif',
        'status_nikah',
        'tmt',
        'keahlian_isyarat',
        'npwp',
        'foto',
    ];
}
