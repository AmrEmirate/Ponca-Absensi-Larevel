<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
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
 */
	class Absensi extends \Eloquent {}
}

namespace App\Models{
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
 */
	class Izin extends \Eloquent {}
}

namespace App\Models{
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
 */
	class MasterLokasi extends \Eloquent {}
}

namespace App\Models{
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
 */
	class User extends \Eloquent {}
}

