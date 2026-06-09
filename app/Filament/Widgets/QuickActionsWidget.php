<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-actions-widget';

    public function getCreateBookingUrl(): string
    {
        return BookingResource::getUrl('create');
    }

    public function getViewAllUrl(): string
    {
        return BookingResource::getUrl('index');
    }
}
