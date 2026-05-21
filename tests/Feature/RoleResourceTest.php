<?php

use App\Models\User;
use App\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use function Pest\Laravel\actingAs;

it('can list roles', function () {
    $admin = User::factory()->create();
    
    // We might need to ensure the role exists for Spatie Permission if the factory doesn't do it
    // But for a simple page load, it should be fine if there are no roles or some roles.
    
    actingAs($admin)
        ->get(RoleResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Roles')
        ->assertSee('Permissions'); // The label we added/kept
});
