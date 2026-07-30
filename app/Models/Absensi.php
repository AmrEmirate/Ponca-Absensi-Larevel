<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read User $user
 * @property-read User $scanner
 * @property int $id
 * @property int $user_id
 * @property int|null $scanner_id
 * @property \Illuminate\Support\Carbon $tanggal
 * @property \Illuminate\Support\Carbon|null $waktu_masuk
 * @property \Illuminate\Support\Carbon|null $waktu_keluar
 * @property string|null $foto_masuk
 * @property string|null $foto_keluar
 * @property string $status
 * @property float|null $lat_masuk
 * @property float|null $lng_masuk
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereFotoKeluar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereFotoMasuk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereLatMasuk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereLngMasuk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereScannerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereTanggal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereWaktuKeluar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absensi whereWaktuMasuk($value)
 * @mixin \Eloquent
 */
class Absensi extends Model
{
    protected $table = 'absensis';

    protected $fillable = [
        'user_id',
        'scanner_id',
        'tanggal',
        'waktu_masuk',
        'waktu_keluar',
        'foto_masuk',
        'foto_keluar',
        'status',
        'lat_masuk',
        'lng_masuk',
        'master_lokasi_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'waktu_masuk' => 'datetime',
        'waktu_keluar' => 'datetime',
        'lat_masuk' => 'double',
        'lng_masuk' => 'double',
        'master_lokasi_id' => 'integer',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function scanner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'scanner_id')->withTrashed();
    }

    public function lokasi(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MasterLokasi::class, 'master_lokasi_id');
    }
}
