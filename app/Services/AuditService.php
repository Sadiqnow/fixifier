<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\MarketplaceSetting;

class AuditService
{
    public function record(string $action, string $target, array $metadata = []): void
    {
        AuditEvent::create(['actor_id' => auth()->id(), 'action' => $action,
            'subject_type' => MarketplaceSetting::class, 'subject_id' => 1,
            'metadata' => ['target' => $target] + $metadata, 'ip_address' => request()->ip(), 'created_at' => now()]);
    }
}
