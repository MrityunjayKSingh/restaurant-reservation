<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_code',
        'table_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'guest_count',
        'special_requests',
        'reservation_date',
        'slot_start',
        'slot_end',
        'status',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'reservation_date' => 'date:Y-m-d',
        'cancelled_at'     => 'datetime',
        'guest_count'      => 'integer',
    ];

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $reservation) {
            if (empty($reservation->reference_code)) {
                $reservation->reference_code = self::generateReferenceCode();
            }
        });
    }

    private static function generateReferenceCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::withTrashed()->where('reference_code', $code)->exists());

        return $code;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('reservation_date', $date);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancellable(): bool
    {
        if (! $this->isConfirmed()) {
            return false;
        }

        $slotDateTime = \Carbon\Carbon::parse(
            $this->reservation_date->format('Y-m-d') . ' ' . $this->slot_start
        );

        $cutoffHours = config('reservation.cancellation_cutoff_hours', 2);

        return now()->addHours($cutoffHours)->lessThan($slotDateTime);
    }

    public function cancel(string $reason = ''): void
    {
        $this->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
