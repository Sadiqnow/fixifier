<?php

namespace App\Support;

use Illuminate\Support\Fluent;

/** Read-only view data; persistence always uses the existing Booking model. */
class AdminBooking extends Fluent
{
    public function currentRound(): ?Fluent
    {
        return $this->rounds->firstWhere('number', $this->current_round);
    }
}
