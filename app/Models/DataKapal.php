<?php

namespace App\Models;

use App\Enums\DataKapalStatus;
use App\Traits\HasEncryptedRouteKey;
use App\Traits\HasUserStamps;
use Carbon\Carbon;
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
        'company_id',
        'galangan_id',
        'nama',
        'deskripsi',
        'spesifikasi',
        'tanggal_mulai_kontrak',
        'durasi_kontrak_hari',
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
        'tanggal_mulai_kontrak' => 'date',
        'durasi_kontrak_hari' => 'integer',
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
            ->logOnly(['kode', 'company_id', 'galangan_id', 'nama', 'deskripsi', 'status', 'tanggal_mulai_kontrak', 'durasi_kontrak_hari'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function kurvaSWorkGroups(): HasMany
    {
        return $this->hasMany(KurvaSWorkGroup::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', DataKapalStatus::Active);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === DataKapalStatus::Active;
    }

    /**
     * Kontrak dianggap terisi lengkap jika tanggal mulai & durasi hari ada.
     */
    public function hasKontrak(): bool
    {
        return ! empty($this->tanggal_mulai_kontrak) && ! empty($this->durasi_kontrak_hari);
    }

    /**
     * Tanggal selesai kontrak = tanggal mulai + durasi hari - 1 (hari kalender, inklusif).
     */
    public function getTanggalSelesaiKontrakAttribute(): ?Carbon
    {
        if (! $this->hasKontrak()) {
            return null;
        }

        return $this->tanggal_mulai_kontrak->copy()->addDays($this->durasi_kontrak_hari - 1);
    }

    public function hasTemplate(string $tipe): bool
    {
        $column = 'template_path_'.$tipe;
        $hasPath = ! empty($this->$column);
        $fileExists = $hasPath ? \Storage::disk('local')->exists($this->$column) : false;

        return $hasPath && $fileExists;
    }

    public function getTemplateFullPath(string $tipe): ?string
    {
        if (! $this->hasTemplate($tipe)) {
            return null;
        }

        $column = 'template_path_'.$tipe;

        return \Storage::disk('local')->path($this->$column);
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
