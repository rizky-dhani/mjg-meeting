<div class="fi-wi-widget p-6">
    <div class="flex items-center gap-3 mb-6">
        <x-filament::icon
            icon="heroicon-o-sparkles"
            class="h-6 w-6 text-primary-500"
        />
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
            Quick Create Booking
        </h3>
    </div>

    <form wire:submit="create" class="space-y-4">
        {{-- Title --}}
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Meeting Title <span class="text-red-500">*</span>
            </label>
            <input
                wire:model="title"
                id="title"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                placeholder="e.g., Weekly Sync"
            >
            @error('title') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Room --}}
        <div>
            <label for="room_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Room <span class="text-red-500">*</span>
            </label>
            <select
                wire:model="room_id"
                id="room_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
            >
                <option value="">Select a room...</option>
                @foreach ($this->getRooms() as $room)
                    <option value="{{ $room['id'] }}">{{ $room['label'] }}</option>
                @endforeach
            </select>
            @error('room_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Date & Time --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Date <span class="text-red-500">*</span>
                </label>
                <input
                    wire:model="date"
                    id="date"
                    type="date"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                >
                @error('date') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="starts_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Start <span class="text-red-500">*</span>
                </label>
                <input
                    wire:model="starts_at"
                    id="starts_at"
                    type="time"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                >
                @error('starts_at') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    End <span class="text-red-500">*</span>
                </label>
                <input
                    wire:model="ends_at"
                    id="ends_at"
                    type="time"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                >
                @error('ends_at') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Description --}}
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Description
            </label>
            <textarea
                wire:model="description"
                id="description"
                rows="2"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                placeholder="Optional notes..."
            ></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between gap-3 pt-2">
            <button
                type="button"
                wire:click="checkAvailability"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" />
                Check
            </button>

            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                <x-filament::icon icon="heroicon-m-plus-circle" class="h-4 w-4" />
                Create Booking
            </button>
        </div>
    </form>
</div>
