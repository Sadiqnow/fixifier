<?php

namespace App\Models;

class TechnicianDecision extends AppendOnlyModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
