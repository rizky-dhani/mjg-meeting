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
                <p class="text-gray-500">This QR code expired at the end of the meeting day ({{ $booking->ends_at->format('M d, Y') }}).</p>
            </div>

        @elseif ($alreadyCheckedIn)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-green-500 text-5xl mb-4">&#10003;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Already Checked In</h2>
                <p class="text-gray-500">You've already recorded your attendance for this meeting.</p>
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
                <p class="text-gray-500">Your check-in has been recorded successfully.</p>
                <div class="mt-6 bg-gray-50 rounded-lg p-4 text-left">
                    <h3 class="font-semibold text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $booking->room->name }} &middot; {{ $booking->room->location?->name }}</p>
                    <p class="text-sm text-gray-500">{{ $booking->starts_at->format('M d, Y H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}</p>
                </div>
            </div>

        @else
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

                <div class="text-center">
                    <p class="text-sm text-gray-500 mb-4">
                        You're checking in as <strong>{{ auth()->user()->name }}</strong>
                    </p>
                    <button
                        wire:click="checkIn"
                        class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                    >
                        Mark Attendance
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
