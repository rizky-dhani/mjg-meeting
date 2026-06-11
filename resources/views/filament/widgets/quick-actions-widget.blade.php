<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Bookings
        </x-slot>

        <div class="flex flex-row gap-3">
            <x-filament::button
                tag="a"
                :href="$this->getCreateBookingUrl()"
                color="success"
                icon="heroicon-m-plus-circle"
                class="w-full justify-center"
            >
                Create Booking
            </x-filament::button>

            <x-filament::button
                tag="a"
                :href="$this->getViewAllUrl()"
                color="info"
                class="w-full justify-center"
            >
                View All Bookings
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
