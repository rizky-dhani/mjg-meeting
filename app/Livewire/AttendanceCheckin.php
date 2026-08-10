<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class AttendanceCheckin extends Component
{
    #[Locked]
    public string $qrToken;

    public ?Booking $booking = null;

    public bool $alreadyCheckedIn = false;

    public bool $isExpired = false;

    public bool $checkedIn = false;

    public bool $confirming = false;

    public bool $loading = true;

    public bool $showGuestForm = false;

    #[Rule('required|exists:users,id')]
    public ?int $selectedUserId = null;

    public string $userSearch = '';

    /** @var array<int, array{id: int, name: string, email: string}> */
    public array $searchResults = [];

    #[Rule('required|string|max:255')]
    public string $guestName = '';

    #[Rule('nullable|string|max:255')]
    public string $guestFrom = '';

    #[Rule('nullable|string|max:255')]
    public string $guestDesignation = '';

    public function mount(string $qrToken): void
    {
        $this->qrToken = $qrToken;
        $this->loadBooking();
    }

    public function loadBooking(): void
    {
        $this->booking = Booking::query()
            ->where('qr_token', $this->qrToken)
            ->whereHas('approvals', function ($q) {
                $q->where('status', 'approved');
            })
            ->with(['room.location', 'attendance'])
            ->first();

        if (! $this->booking) {
            $this->booking = null;
            $this->loading = false;

            return;
        }

        if ($this->booking->isExpired()) {
            $this->isExpired = true;
            $this->loading = false;

            return;
        }

        $this->loading = false;
    }

    public function updatedSelectedUserId(): void
    {
        $this->alreadyCheckedIn = false;

        if ($this->selectedUserId && $this->booking) {
            $this->alreadyCheckedIn = $this->booking->attendance()
                ->where('user_id', $this->selectedUserId)
                ->exists();
        }
    }

    public function updatedUserSearch(): void
    {
        $this->searchUsers();
    }

    public function searchUsers(): void
    {
        $search = trim($this->userSearch);

        if (strlen($search) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->toArray();
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->userSearch = '';
        $this->searchResults = [];
        $this->showGuestForm = false;
        $this->updatedSelectedUserId();
    }

    public function toggleGuestForm(): void
    {
        $this->showGuestForm = ! $this->showGuestForm;

        if ($this->showGuestForm) {
            $this->selectedUserId = null;
            $this->alreadyCheckedIn = false;
        }
    }

    public function confirmCheckIn(): void
    {
        if ($this->alreadyCheckedIn || $this->isExpired || ! $this->booking) {
            return;
        }

        $this->confirming = true;
    }

    public function cancelCheckIn(): void
    {
        $this->confirming = false;
    }

    public function checkIn(): void
    {
        if (! $this->booking || $this->isExpired) {
            $this->confirming = false;

            return;
        }

        if ($this->showGuestForm) {
            $this->validate([
                'guestName' => 'required|string|max:255',
                'guestFrom' => 'nullable|string|max:255',
                'guestDesignation' => 'nullable|string|max:255',
            ]);

            $alreadyCheckedIn = $this->booking->attendance()
                ->whereNull('user_id')
                ->where('guest_name', $this->guestName)
                ->exists();

            if ($alreadyCheckedIn) {
                $this->alreadyCheckedIn = true;
                $this->confirming = false;

                return;
            }

            Attendance::create([
                'booking_id' => $this->booking->id,
                'user_id' => null,
                'guest_name' => $this->guestName,
                'guest_from' => $this->guestFrom,
                'guest_designation' => $this->guestDesignation,
                'checked_in_at' => now(),
            ]);
        } else {
            $this->validate([
                'selectedUserId' => 'required|exists:users,id',
            ]);

            if ($this->alreadyCheckedIn) {
                $this->confirming = false;

                return;
            }

            Attendance::create([
                'booking_id' => $this->booking->id,
                'user_id' => $this->selectedUserId,
                'checked_in_at' => now(),
            ]);
        }

        $this->checkedIn = true;
        $this->confirming = false;
    }

    public function getSelectedUserName(): ?string
    {
        if (! $this->selectedUserId) {
            return null;
        }

        return User::find($this->selectedUserId)?->name;
    }

    public function render()
    {
        return view('livewire.attendance-checkin')
            ->layout('layouts.app');
    }
}
