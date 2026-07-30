<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Absensi> $absensi
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Absensi> $absensiScanned
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Izin> $izin
 * @property int $id
 * @property string $nik
 * @property string $nama
 * @property string|null $jabatan
 * @property string $email
 * @property string $password
 * @property string|null $foto_referensi
 * @property string|null $foto_profil
 * @property string $role
 * @property bool $is_active
 * @property int $gaji_perhari
 * @property string $hari_kerja
 * @property string $jam_masuk_kerja
 * @property string $jam_keluar_kerja
 * @property string $face_reverification_status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $absensi_count
 * @property-read int|null $absensi_scanned_count
 * @property-read int|null $izin_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFaceReverificationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFotoProfil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFotoReferensi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGajiPerhari($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHariKerja($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereJabatan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereJamKeluarKerja($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereJamMasukKerja($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNama($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNik($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use SoftDeletes;

    protected $fillable = [
        'nik',
        'nama',
        'jabatan',
        'email',
        'password',
        'foto_referensi',
        'foto_profil',
        'role',
        'is_active',
        'gaji_perhari',
        'hari_kerja',
        'jam_masuk_kerja',
        'jam_keluar_kerja',
        'master_lokasi_id',
        'face_reverification_status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'gaji_perhari' => 'integer',
        'master_lokasi_id' => 'integer',
    ];

    public function lokasi(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MasterLokasi::class, 'master_lokasi_id');
    }

    public function absensi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Absensi::class, 'user_id');
    }

    public function absensiScanned(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Absensi::class, 'scanner_id');
    }

    public function izin(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Izin::class, 'user_id');
    }
}
