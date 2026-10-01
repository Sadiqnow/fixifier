<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Quotation extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['expires_at'=>'datetime', 'accepted_at'=>'datetime', 'items'=>'array', 'amount_minor'=>'integer']; }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    protected static function booted(): void {
        static::updating(function ($q) {
            if ($q->getOriginal('accepted_at') && $q->isDirty()) throw new \LogicException('Accepted quotations are immutable.');
        });
        static::deleting(fn () => throw new \LogicException('Quote history must be retained.'));
    }
}
