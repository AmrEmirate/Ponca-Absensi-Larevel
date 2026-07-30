<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read User $user
 * @property int $id
 * @property int $user_id
 * @property string $jenis_izin
 * @property string $deskripsi
 * @property string|null $foto_url
 * @property string $status
 * @property \Illuminate\Support\Carbon $tanggal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereDeskripsi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereFotoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereJenisIzin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereTanggal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Izin whereUserId($value)
 * @mixin \Eloquent
 */
class Izin extends Model
{
    protected $table = 'izins';

    protected $fillable = [
        'user_id',
        'jenis_izin',
        'deskripsi',
        'foto_url',
        'status',
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
