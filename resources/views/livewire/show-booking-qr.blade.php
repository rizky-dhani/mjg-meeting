<div class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        @if ($notFound)
            <div class="bg-white shadow-lg rounded-2xl p-8 text-center">
                <div class="text-red-500 text-5xl mb-4">&#10060;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Invalid QR Code</h2>
                <p class="text-gray-500">This QR code is not valid or the booking has been cancelled.</p>
            </div>
        @elseif ($booking)
            <div class="bg-white shadow-lg rounded-2xl p-8 text-center">
                <div class="mb-6">
                    <h1 class="text-xl font-bold text-gray-900">{{ $booking->title }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->room->name }}
                        @if ($booking->room->location)
                            &middot; {{ $booking->room->location->name }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('l') }}, {{ strtoupper($booking->starts_at->format('d F Y')) }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}
                    </p>
                </div>

                <div class="bg-white p-4 inline-block rounded-xl shadow-inner">
                    <img
                        src="{{ asset('storage/' . $booking->qr_code) }}"
                        alt="QR Code for {{ $booking->title }}"
                        class="w-64 h-64 object-contain mx-auto"
                    />
                </div>

                <p class="text-xs text-gray-400 mt-6">
                    Scan this QR code at the meeting room for attendance record.
                </p>
            </div>
        @endif
    </div>
</div>
