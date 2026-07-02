<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Support\Approvals\Evaluation\ApprovalEvaluator;
use App\Support\Approvals\Models\Approval;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Milon\Barcode\Facades\DNS2DFacade;

class BookingApprovalService
{
    public function approve(Booking $booking, ?string $reason = null): void
    {
        $step = $booking->currentActionableStep();
        if ($step === null || $step->role === null) {
            Notification::make()
                ->title('No pending steps to approve')
                ->warning()
                ->send();
            return;
        }

        $flow = $booking->approvalFlow();
        if ($flow === null) {
            return;
        }

        Approval::create([
            'approver_id' => auth()->id(),
            'approver_type' => User::class,
            'approvable_id' => $booking->id,
            'approvable_type' => Booking::class,
            'status' => 'approved',
            'key' => $flow->name,
            'approval_by' => $step->role->name,
            'approval_flow_step_id' => $step->id,
            'reason' => $reason,
        ]);

        $booking->refresh();

        if ($booking->isApproved()) {
            $this->generateQr($booking);
            $this->createAttendance($booking);
            $booking->user->notify(new \App\Notifications\BookingApproved($booking));
        } else {
            $this->notifyNextApprovers($booking);
        }

        Notification::make()
            ->title('Booking approved successfully')
            ->success()
            ->send();
    }

    public function reject(Booking $booking, ?string $reason = null): void
    {
        $step = $booking->currentActionableStep();
        if ($step === null || $step->role === null) {
            Notification::make()
                ->title('No pending steps to approve')
                ->warning()
                ->send();
            return;
        }

        $flow = $booking->approvalFlow();
        if ($flow === null) {
            return;
        }

        Approval::create([
            'approver_id' => auth()->id(),
            'approver_type' => User::class,
            'approvable_id' => $booking->id,
            'approvable_type' => Booking::class,
            'status' => 'rejected',
            'key' => $flow->name,
            'approval_by' => $step->role->name,
            'approval_flow_step_id' => $step->id,
            'reason' => $reason,
        ]);

        $booking->refresh();
        $booking->user->notify(new \App\Notifications\BookingRejected($booking, $reason));

        Notification::make()
            ->title('Booking rejected')
            ->warning()
            ->send();
    }

    private function generateQr(Booking $booking): void
    {
        $qrToken = (string) Str::uuid();
        $qrCodeUrl = url('/attendance/' . $qrToken);
        $qrPng = DNS2DFacade::getBarcodePNG($qrCodeUrl, 'QRCODE', 8, 8);
        $qrPath = sprintf('bookings/QR-%s.png', $booking->booking_number);
        Storage::disk('public')->put($qrPath, base64_decode($qrPng));

        $booking->update([
            'qr_token' => $qrToken,
            'qr_code' => $qrPath,
        ]);
    }

    private function createAttendance(Booking $booking): void
    {
        $booking->attendance()->create([
            'user_id' => $booking->user_id,
            'checked_in_at' => now(),
        ]);
    }

    private function notifyNextApprovers(Booking $booking): void
    {
        $nextStep = $booking->currentActionableStep();
        if ($nextStep !== null) {
            $approvers = ApprovalEvaluator::getEligibleApprovers($booking, $nextStep);
            \Illuminate\Support\Facades\Notification::send(
                $approvers,
                new \App\Notifications\BookingNeedsApproval($booking)
            );
        }
    }
}
