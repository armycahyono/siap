<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;

class CbtJenis extends Model
{
    protected $connection = 'cbt';

    protected $table = 'cbt_jenis';

    protected $primaryKey = 'id_jenis';

    public $timestamps = false;

    protected $fillable = [
        'nama_jenis',
        'kode_jenis',
    ];
}
