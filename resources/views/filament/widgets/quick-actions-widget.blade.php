<div class="fi-wi-widget p-6">
    <div class="flex flex-col items-center gap-4">
        <x-filament::icon
            icon="heroicon-o-calendar-days"
            class="h-12 w-12 text-gray-400 dark:text-gray-500"
        />

        <div class="text-center">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                Need a meeting room?
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Create a new booking for your team
            </p>
        </div>

        <x-filament::button
            :href="$getCreateBookingUrl()"
            tag="a"
            icon="heroicon-m-plus-circle"
            color="primary"
            class="w-full justify-center"
        >
            Create Booking
        </x-filament::button>

        <x-filament::link
            :href="$getViewAllUrl()"
            color="primary"
            class="text-sm"
        >
            View all bookings &rarr;
        </x-filament::link>
    </div>
</div>
