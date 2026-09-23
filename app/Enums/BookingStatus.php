<?php
namespace App\Enums;
enum BookingStatus:string {
 case Requested='requested'; case Quoted='quoted'; case Confirmed='confirmed'; case InProgress='in_progress';
 case EvidenceSubmitted='evidence_submitted'; case Completed='completed'; case Disputed='disputed'; case Cancelled='cancelled';
}
