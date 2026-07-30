<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nama_place
 * @property float $latitude
 * @property float $longitude
 * @property float $radius
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereNamaPlace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereRadius($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterLokasi whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MasterLokasi extends Model
{
    protected $table = 'master_lokasis';

    protected $fillable = [
        'nama_place',
        'tipe',
        'alamat',
        'latitude',
        'longitude',
        'radius',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'radius' => 'double',
        'is_active' => 'boolean',
    ];

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'master_lokasi_id');
    }

    public function absensis(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Absensi::class, 'master_lokasi_id');
    }
}
