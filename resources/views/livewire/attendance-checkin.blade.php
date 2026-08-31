<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-lg w-full space-y-8">
        @if ($loading)
            <div class="text-center">
                <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="mt-3 text-gray-500">Loading meeting details...</p>
            </div>

        @elseif (! $booking)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-red-500 text-5xl mb-4">&#10060;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Invalid QR Code</h2>
                <p class="text-gray-500">This QR code is not valid or the booking has been cancelled.</p>
            </div>

        @elseif ($isExpired)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-yellow-500 text-5xl mb-4">&#9203;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">QR Code Expired</h2>
                <p class="text-gray-500">This QR code expired at the end of the meeting day.</p>
            </div>

        @elseif ($alreadyCheckedIn)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-green-500 text-5xl mb-4">&#10003;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Already Checked In</h2>
                <p class="text-gray-500">
                    @if ($showGuestForm)
                        <strong>{{ $guestName }}</strong> has already recorded attendance for this meeting.
                    @else
                        This user has already recorded attendance for this meeting.
                    @endif
                </p>
                <div class="mt-6 bg-gray-50 rounded-lg p-4 text-left">
                    <h3 class="font-semibold text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $booking->room->name }} &middot; {{ $booking->room->location?->name }}</p>
                    <p class="text-sm text-gray-500">{{ $booking->starts_at->format('M d, Y H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}</p>
                </div>
            </div>

        @elseif ($checkedIn)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-green-500 text-5xl mb-4">&#10003;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Attendance Recorded!</h2>
                <p class="text-gray-500">
                    @if ($showGuestForm)
                        Check-in for <strong>{{ $guestName }}</strong> has been recorded successfully.
                    @else
                        Check-in has been recorded successfully.
                    @endif
                </p>
                <div class="mt-6 bg-gray-50 rounded-lg p-4 text-left">
                    <h3 class="font-semibold text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $booking->room->name }} &middot; {{ $booking->room->location?->name }}</p>
                    <p class="text-sm text-gray-500">{{ $booking->starts_at->format('M d, Y H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}</p>
                </div>
            </div>

        @elseif ($confirming)
            <div class="bg-white shadow rounded-lg p-8">
                <div class="text-center mb-6">
                    <div class="text-amber-500 text-5xl mb-4">&#9888;</div>
                    <h2 class="text-2xl font-bold text-gray-900">Confirm Check-In</h2>
                    <p class="text-gray-500 mt-2">Please confirm your attendance for this meeting.</p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-lg text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->room->name }}
                        @if($booking->room->location)
                            &middot; {{ $booking->room->location->name }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->starts_at->format('l, M d, Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}
                    </p>
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-500 mb-4">
                        Checking in as <strong>{{ $showGuestForm ? $guestName : $this->getSelectedUserName() }}</strong>
                    </p>
                    <div class="flex gap-3">
                        <button
                            wire:click="cancelCheckIn"
                            class="flex-1 bg-white text-gray-700 py-3 px-6 rounded-lg font-medium border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            wire:click="checkIn"
                            class="flex-1 bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                        >
                            Confirm Check-In
                        </button>
                    </div>
                </div>
            </div>

        @else
            {{-- Check-in form --}}
            <div class="bg-white shadow rounded-lg p-8">
                <div class="text-center mb-6">
                    <div class="text-indigo-500 text-5xl mb-4">&#128197;</div>
                    <h2 class="text-2xl font-bold text-gray-900">Meeting Check-In</h2>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-lg text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->room->name }}
                        @if($booking->room->location)
                            &middot; {{ $booking->room->location->name }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->starts_at->format('l, M d, Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}
                    </p>
                    @if($booking->description)
                        <p class="text-sm text-gray-600 mt-2">{{ $booking->description }}</p>
                    @endif
                </div>

                @if ($showGuestForm)
                    {{-- Guest check-in form --}}
                    <form wire:submit="checkIn" class="space-y-4">
                        <p class="text-sm text-gray-600">Enter your details to check in as a guest:</p>

                        <div>
                            <label for="guestName" class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                            <input
                                wire:model="guestName"
                                id="guestName"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Your full name"
                            >
                            @error('guestName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="guestFrom" class="block text-sm font-medium text-gray-700">From</label>
                            <input
                                wire:model="guestFrom"
                                id="guestFrom"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Acme Corp"
                            >
                        </div>

                        <div>
                            <label for="guestDesignation" class="block text-sm font-medium text-gray-700">Designation</label>
                            <input
                                wire:model="guestDesignation"
                                id="guestDesignation"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Vendor PIC"
                            >
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                        >
                            Check In as Guest
                        </button>
                    </form>

                    <div class="mt-4 text-center">
                        <button
                            wire:click="toggleGuestForm"
                            class="text-sm text-indigo-600 hover:text-indigo-500 font-medium"
                        >
                            &larr; Back to staff check-in
                        </button>
                    </div>
                @else
                    {{-- Staff check-in: user search --}}
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search your name</label>
                            <input
                                wire:model.live.debounce.300ms="userSearch"
                                type="text"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Type name, email, or employee code..."
                            >
                        </div>

                        @if (count($searchResults) > 0)
                            <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-60 overflow-y-auto">
                                @foreach ($searchResults as $result)
                                    <button
                                        type="button"
                                        wire:click="selectUser({{ $result['id'] }})"
                                        class="w-full text-left px-4 py-3 hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none transition-colors {{ $selectedUserId == $result['id'] ? 'bg-indigo-50 border-l-4 border-indigo-600' : '' }}"
                                    >
                                        <div class="font-medium text-gray-900">{{ $result['name'] }}</div>
                                        <div class="text-sm text-gray-500">{{ $result['email'] }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (strlen($userSearch) >= 2)
                            <p class="text-sm text-gray-500 text-center py-4">No users found matching "{{ $userSearch }}"</p>
                        @endif

                        @if ($selectedUserId)
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="text-green-500 text-xl mr-3">&#10003;</div>
                                    <div>
                                        <p class="font-medium text-green-800">Selected: {{ $this->getSelectedUserName() }}</p>
                                        <p class="text-sm text-green-600">Click below to confirm your attendance.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @error('selectedUserId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <button
                            wire:click="confirmCheckIn"
                            @disabled(! $selectedUserId)
                            class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Mark Attendance
                        </button>

                        <div class="text-center">
                            <button
                                wire:click="toggleGuestForm"
                                class="text-sm text-gray-500 hover:text-gray-700 font-medium"
                            >
                                Not a staff member? Check in as guest &rarr;
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
