<?php

namespace App\Providers;

use App\Auth\IdentityUserProvider;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::provider('medquest_users', function ($app, array $config) {
            return new IdentityUserProvider(
                $app['hash'],
                config('auth.providers.users.model'),
            );
        });

        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);

        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }

            if ($user->hasRole('Head')) {
                if (in_array($ability, [
                    'view_any_booking',
                    'view_booking',
                ])) {
                    return true;
                }

                if (in_array($ability, [
                    'create_booking',
                    'update_booking',
                    'delete_booking',
                ])) {
                    return false;
                }

                return null;
            }

            if ($user->hasRole('Admin')) {
                if (in_array($ability, [
                    'view_any_booking',
                    'view_booking',
                    'create_booking',
                    'update_booking',
                    'delete_booking',
                ])) {
                    return true;
                }

                return null;
            }
        });

        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
