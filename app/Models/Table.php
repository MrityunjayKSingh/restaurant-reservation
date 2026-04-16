<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'table_number',
        'capacity',
        'location',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'capacity'  => 'integer',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public function scopeWithMinCapacity($query, int $capacity)
    {
        return $query->where('capacity', '>=', $capacity);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAvailableForSlot(string $date, string $slotStart): bool
    {
        return ! $this->reservations()
            ->where('reservation_date', $date)
            ->where('slot_start', $slotStart)
            ->where('status', 'confirmed')
            ->exists();
    }
}
