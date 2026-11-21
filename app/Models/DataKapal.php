<?php

namespace App\Models;

use App\Enums\DataKapalStatus;
use App\Services\FilePathService;
use App\Traits\HasEncryptedRouteKey;
use App\Traits\HasUserStamps;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DataKapal extends Model
{
    use HasEncryptedRouteKey, HasFactory, SoftDeletes;
    use HasUserStamps, LogsActivity;

    protected $table = 'data_kapal';

    /**
     * Jenis template Word per kapal (kolom template_path_{tipe}) → label UI.
     * Whitelist: tipe di luar daftar ini ditolak saat upload/hapus template.
     */
    public const TEMPLATE_TIPES = [
        'harian' => 'Harian',
        'mingguan' => 'Mingguan',
        'bulanan' => 'Bulanan',
        'nc' => 'Form NC',
    ];

    protected $fillable = [
        'owner_id',
        'galangan_id',
        'nama',
        'deskripsi',
        'nama_pekerjaan',
        'nomor_kontrak_galangan',
        'nomor_kontrak_pengawas',
        'nomor_kontrak_gambar',
        'penandatangan',
        'spesifikasi',
        'tanggal_mulai_pelaksanaan',
        'waktu_pelaksanaan_hari',
        'minggu_bulan_map',
        'template_path_harian',
        'template_path_mingguan',
        'template_path_bulanan',
        'template_path_nc',
        'status',
    ];

    /**
     * Karakter kode: uppercase + digit tanpa karakter ambigu (0/O, 1/I/L).
     */
    private const KODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $casts = [
        'status' => DataKapalStatus::class,
        'spesifikasi' => 'array',
        'penandatangan' => 'array',
        'tanggal_mulai_pelaksanaan' => 'date',
        'waktu_pelaksanaan_hari' => 'integer',
        'minggu_bulan_map' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DataKapal $dataKapal) {
            if (empty($dataKapal->kode)) {
                $dataKapal->kode = static::generateKode();
            }
        });
    }

    /**
     * Generate kode unik 6 karakter. withTrashed karena index unik tetap
     * ditempati record yang sudah soft-deleted.
     */
    public static function generateKode(): string
    {
        $length = strlen(self::KODE_ALPHABET);

        do {
            $kode = '';
            for ($i = 0; $i < 6; $i++) {
                $kode .= self::KODE_ALPHABET[random_int(0, $length - 1)];
            }
        } while (static::withTrashed()->where('kode', $kode)->exists());

        return $kode;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data_kapal')
            ->logOnly(['kode', 'owner_id', 'galangan_id', 'nama', 'nama_pekerjaan', 'nomor_kontrak_galangan', 'nomor_kontrak_pengawas', 'nomor_kontrak_gambar', 'deskripsi', 'status', 'tanggal_mulai_pelaksanaan', 'waktu_pelaksanaan_hari'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function galangan(): BelongsTo
    {
        return $this->belongsTo(Galangan::class);
    }

    public function laporanHarian(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function laporanMingguan(): HasMany
    {
        return $this->hasMany(LaporanMingguan::class);
    }

    public function laporanBulanan(): HasMany
    {
        return $this->hasMany(LaporanBulanan::class);
    }

    public function laporanNc(): HasMany
    {
        return $this->hasMany(LaporanNc::class);
    }

    public function kurvaSWorkGroups(): HasMany
    {
        return $this->hasMany(KurvaSWorkGroup::class)->orderBy('sort_order');
    }

    public function drawingRegisters(): HasMany
    {
        return $this->hasMany(DrawingRegister::class)->orderBy('nama');
    }

    public function projectVendors(): HasMany
    {
        return $this->hasMany(ProjectVendor::class)->orderBy('nama');
    }

    public function projectMaterials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class)->orderBy('nama_item');
    }

    public function pengujians(): HasMany
    {
        return $this->hasMany(Pengujian::class)->orderBy('tanggal');
    }

    public function rapats(): HasMany
    {
        return $this->hasMany(Rapat::class)->orderBy('tanggal');
    }

    /**
     * Penandatangan ke-N (1-based) untuk lembar pengesahan laporan.
     *
     * @return array{label: string, nama: string, jabatan: string, instansi: string}
     */
    public function getPenandatangan(int $ke): array
    {
        $row = collect($this->penandatangan ?? [])->values()->get($ke - 1) ?? [];

        return [
            'label' => $row['label'] ?? '',
            'nama' => $row['nama'] ?? '',
            'jabatan' => $row['jabatan'] ?? '',
            'instansi' => $row['instansi'] ?? '',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', DataKapalStatus::Active);
    }

    public function scopeByOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === DataKapalStatus::Active;
    }

    /**
     * Pelaksanaan dianggap terisi lengkap jika tanggal mulai & waktu hari ada.
     */
    public function hasPelaksanaan(): bool
    {
        return ! empty($this->tanggal_mulai_pelaksanaan) && ! empty($this->waktu_pelaksanaan_hari);
    }

    /**
     * Tanggal selesai pelaksanaan = tanggal mulai + waktu hari - 1 (hari kalender, inklusif).
     */
    public function getTanggalSelesaiPelaksanaanAttribute(): ?Carbon
    {
        if (! $this->hasPelaksanaan()) {
            return null;
        }

        return $this->tanggal_mulai_pelaksanaan->copy()->addDays($this->waktu_pelaksanaan_hari - 1);
    }

    /**
     * Sisa hari kalender sampai pelaksanaan selesai (negatif = lewat waktu, null = pelaksanaan belum diisi).
     */
    public function sisaHariPelaksanaan(): ?int
    {
        return $this->tanggal_selesai_pelaksanaan
            ? (int) now()->startOfDay()->diffInDays($this->tanggal_selesai_pelaksanaan, false)
            : null;
    }

    /**
     * Realisasi & sisa hari pelaksanaan s.d. cutoff (inklusif, clamp jangka).
     *
     * @return array{0: int, 1: int} [realisasi, sisa]
     */
    public function progresWaktuPelaksanaan(?CarbonInterface $cutoff): array
    {
        $jangka = (int) ($this->waktu_pelaksanaan_hari ?? 0);
        $realisasi = ($this->tanggal_mulai_pelaksanaan && $cutoff)
            ? min(max(0, (int) $this->tanggal_mulai_pelaksanaan->diffInDays($cutoff) + 1), $jangka)
            : 0;

        return [$realisasi, max(0, $jangka - $realisasi)];
    }

    /**
     * Baris INFORMASI UMUM (label => nilai) untuk dokumen laporan —
     * dipakai bersama laporan mingguan & bulanan.
     *
     * @return array<string, string>
     */
    public static function infoUmumRows(?self $dk, ?CarbonInterface $cutoff): array
    {
        $jangka = (int) ($dk?->waktu_pelaksanaan_hari ?? 0);
        [$realisasi, $sisa] = $dk?->progresWaktuPelaksanaan($cutoff) ?? [0, 0];
        $tanggal = fn ($d) => $d ? $d->copy()->locale('id')->translatedFormat('d F Y') : '-';

        return [
            'PEMILIK PEKERJAAN' => trim(($dk?->owner?->name ?? '-').' '.($dk?->owner?->address ?? '')),
            'NAMA PEKERJAAN' => $dk?->nama_pekerjaan ?: ($dk?->nama ?? '-'),
            'NAMA GALANGAN' => trim(($dk?->galangan?->nama ?? '-').' '.($dk?->galangan?->alamat ?? '')),
            'NOMOR KONTRAK GALANGAN' => $dk?->nomor_kontrak_galangan ?: '-',
            'KONSULTAN PENGAWAS' => 'PT. BIRO KLASIFIKASI INDONESIA (PERSERO) SBU Marine & Offshore Migas',
            'NOMOR KONTRAK PENGAWAS' => $dk?->nomor_kontrak_pengawas ?: '-',
            'TANGGAL MULAI PELAKSANAAN' => $tanggal($dk?->tanggal_mulai_pelaksanaan),
            'WAKTU PELAKSANAAN' => $jangka > 0 ? $jangka.' Hari Kalender' : '-',
            'REALISASI WAKTU PELAKSANAAN' => $jangka > 0 ? $realisasi.' Hari Kalender' : '-',
            'SISA WAKTU PELAKSANAAN' => $jangka > 0 ? $sisa.' Hari Kalender' : '-',
        ];
    }

    public function hasTemplate(string $tipe): bool
    {
        $column = 'template_path_'.$tipe;
        $hasPath = ! empty($this->$column);
        $fileExists = $hasPath ? \Storage::disk()->exists($this->$column) : false;

        return $hasPath && $fileExists;
    }

    public function getTemplateFullPath(string $tipe): ?string
    {
        if (! $this->hasTemplate($tipe)) {
            return null;
        }

        $column = 'template_path_'.$tipe;

        return app(FilePathService::class)->localPath($this->$column);
    }

    public function hasAnyTemplate(): bool
    {
        return collect(array_keys(self::TEMPLATE_TIPES))->contains(fn ($tipe) => $this->hasTemplate($tipe));
    }

    public function getLaporanCount(string $tipe): int
    {
        return match ($tipe) {
            'harian' => $this->laporan_harian_count ?? $this->laporanHarian()->count(),
            'mingguan' => $this->laporan_mingguan_count ?? $this->laporanMingguan()->count(),
            'bulanan' => $this->laporan_bulanan_count ?? $this->laporanBulanan()->count(),
            default => 0,
        };
    }
}
