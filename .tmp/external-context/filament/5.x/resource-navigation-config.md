---
source: Context7 API
library: filament
package: filament/filament
topic: Resource Navigation Configuration
fetched: 2026-05-20
official_docs: https://filamentphp.com/docs/5.x/resources/overview
---

# Resource Navigation Configuration in Filament v5

## Static Properties

### navigationGroup()

Group resources under a specific navigation label:

```php
protected static string | UnitEnum | null $navigationGroup = 'Shop';
```

**Options:**
- String: `'Settings'`
- UnitEnum: `NavigationGroup::Settings`
- Null: No group (root level)

### shouldRegisterNavigation()

Control whether the resource appears in navigation:

```php
// Hide from navigation
protected static bool $shouldRegisterNavigation = false;
```

**Default:** `true`

**Use cases:**
- Hidden admin resources
- Resources only accessible via custom links
- Debug/testing resources

## Dynamic Methods

### getNavigationGroup()

Return a dynamic group label:

```php
public static function getNavigationGroup(): ?string
{
    return auth()->user()->isAdmin() ? 'Admin' : 'User Panel';
}
```

### shouldRegisterNavigation()

Dynamic visibility control:

```php
public static function shouldRegisterNavigation(): bool
{
    return auth()->user()->can('viewAny', User::class);
}
```

## Complete Configuration Example

```php
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // === Navigation Configuration ===

    // Group resources under "Management"
    protected static string | UnitEnum | null $navigationGroup = 'Management';

    // Icon for navigation item
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUser;

    // Icon when navigation is active
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::SolidUser;

    // Custom label text
    protected static ?string $navigationLabel = 'User Management';

    // Sort order (lower numbers appear first)
    protected static ?int $navigationSort = 3;

    // Hide from navigation entirely
    protected static bool $shouldRegisterNavigation = true;

    // Parent item for nested navigation
    protected static ?string $navigationParentItem = null;

    // === Methods (Dynamic Alternatives) ===

    public static function getNavigationGroup(): ?string
    {
        // Return localized or conditional group label
        return __('filament.navigation.groups.management');
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        // Dynamic icon based on user role
        return auth()->user()->isAdmin() 
            ? Heroicon::OutlinedShieldCheck 
            : Heroicon::OutlinedUser;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Only show to authorized users
        return auth()->user()->can('viewAny', User::class);
    }

    public static function getNavigationSort(): ?int
    {
        // Dynamic sort order
        return auth()->user()->isAdmin() ? 1 : 10;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
```

## Panel-Level Configuration

### Register Multiple Resource Configurations

```php
use App\Filament\Resources\OrderResource;

public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            // "Active orders" configuration
            OrderResource::make('active')
                ->navigationLabel('Active Orders')
                ->navigationGroup('Orders'),

            // "Archived orders" configuration
            OrderResource::make('archived')
                ->navigationLabel('Archived Orders')
                ->navigationGroup('Orders')
                ->archived(),
        ]);
}
```

### Custom Navigation Groups in Panel

```php
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

public function panel(Panel $panel): Panel
{
    return $panel
        ->navigationGroups([
            NavigationGroup::make()
                ->label('Shop')
                ->icon(Heroicon::OutlinedShoppingCart),

            NavigationGroup::make()
                ->label('Blog')
                ->icon(Heroicon::OutlinedPencil),

            NavigationGroup::make()
                ->label(fn (): string => __('navigation.settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->collapsed(), // Collapsed by default
        ]);
}
```

## Common Patterns

### Conditional Navigation Group

```php
public static function getNavigationGroup(): ?string
{
    if (auth()->user()->isAdmin()) {
        return 'Admin';
    }
    
    return 'Dashboard';
}
```

### Role-Based Navigation Visibility

```php
public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();
    
    return $user->hasRole('admin') || $user->hasRole('manager');
}
```

### Multi-Tenant Navigation Group

```php
public static function getNavigationGroup(): ?string
{
    return auth()->user()->tenant->name ?? 'Default';
}
```

## API Reference Summary

| Property/Method | Type | Default | Description |
|-----------------|------|---------|-------------|
| `$navigationGroup` | `string \| UnitEnum \| null` | `null` | Group label |
| `$navigationIcon` | `string \| BackedEnum \| null` | `null` | Navigation icon |
| `$navigationLabel` | `?string` | Auto-generated | Custom label |
| `$navigationSort` | `?int` | `null` | Sort order |
| `$shouldRegisterNavigation` | `bool` | `true` | Show in nav |
| `getNavigationGroup()` | `?string` | - | Dynamic group |
| `getNavigationIcon()` | `string \| BackedEnum \| Htmlable \| null` | - | Dynamic icon |
| `shouldRegisterNavigation()` | `bool` | `true` | Dynamic visibility |
| `getNavigationSort()` | `?int` | - | Dynamic sort |