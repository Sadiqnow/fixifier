<?php

namespace App\Models;

class EvidenceFlag extends AppendOnlyModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
