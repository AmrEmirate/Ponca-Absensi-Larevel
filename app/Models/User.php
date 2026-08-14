<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'nama',
        'nik',
        'email',
        'password',
        'role',
        'jabatan',
        'gaji_perhari',
        'hari_kerja',
        'jam_masuk_kerja',
        'jam_keluar_kerja',
        'master_lokasi_id',
        'foto_profil',
        'foto_referensi',
        'location',
        'avatar',
        'status',
        'is_active',
        'face_reverification_status',
        'last_login_at',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'gaji_perhari' => 'integer',
            'master_lokasi_id' => 'integer',
        ];
    }

    public function lokasi()
    {
        return $this->belongsTo(MasterLokasi::class, 'master_lokasi_id');
    }

    public function absensi()
    {
        return $this->hasMany(Absensi::class, 'user_id');
    }

    public function absensiScanned()
    {
        return $this->hasMany(Absensi::class, 'scanner_id');
    }

    public function izin()
    {
        return $this->hasMany(Izin::class, 'user_id');
    }
}
