---
source: Context7 API
library: filament
package: filament/filament
topic: Navigation Icon
fetched: 2026-05-20
official_docs: https://filamentphp.com/docs/5.x/resources/overview
---

# Navigation Icon in Filament v5

## Recommended Approach: `$navigationIcon` Property

Set the `$navigationIcon` static property to assign an icon to your resource's navigation item:

```php
use BackedEnum;

protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
```

### API Signature

```php
protected static ?string $navigationIcon = 'heroicon-o-user-group';
// or
protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
```

## Using Heroicon Import

Import icons from `Filament\Support\Icons\Heroicon`:

```php
use Filament\Support\Icons\Heroicon;

// String-based approach (outlined)
protected static ?string $navigationIcon = 'heroicon-o-user-group';

// Enum-based approach (recommended)
protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUser;
```

### Available Heroicon Variants

```php
Heroicon::OutlinedUser          // heroicon-o-user
Heroicon::OutlinedUsers         // heroicon-o-users
Heroicon::OutlinedUserCircle    // heroicon-o-user-circle
Heroicon::OutlinedUserGroup     // heroicon-o-user-group
```

## Recommended Heroicon for "Users" Resource

For a Users resource, the recommended icon is:

```php
use Filament\Support\Icons\Heroicon;

// Option 1: Outlined user icon
protected static ?string $navigationIcon = 'heroicon-o-user';

// Option 2: Using Heroicon enum (recommended)
protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUser;

// Option 3: Multiple users (for user management)
protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;
```

## Dynamic Icon with `getNavigationIcon()` Method

For conditional or dynamic icons, use the `getNavigationIcon()` method:

```php
use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;

public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
{
    return 'heroicon-o-user-group';
}
```

### Dynamic Icon Based on Condition

```php
public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
{
    return auth()->user()->isAdmin() 
        ? 'heroicon-o-shield-check' 
        : 'heroicon-o-user';
}
```

## Custom Active Navigation Icon

Use `$activeNavigationIcon` for a different icon when the item is active:

```php
use Filament\Support\Icons\Heroicon;

protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::OutlinedUser;
```

## Full Example: UsersResource

```php
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // Navigation group
    protected static string | UnitEnum | null $navigationGroup = 'Management';

    // Navigation icon - recommended for Users resource
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUser;

    // Alternative: Multiple users for user management
    // protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    // Custom active icon
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::SolidUser;

    // Navigation sort order
    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
```

## Available Navigation Static Properties Summary

| Property | Type | Description |
|----------|------|-------------|
| `$navigationGroup` | `string \| UnitEnum \| null` | Group resources under a label |
| `$navigationIcon` | `string \| BackedEnum \| null` | Set the navigation icon |
| `$activeNavigationIcon` | `string \| BackedEnum \| null` | Icon when active |
| `$navigationLabel` | `?string` | Custom label text |
| `$navigationSort` | `?int` | Sort order in navigation |
| `$shouldRegisterNavigation` | `bool` | Show/hide from navigation |
| `$navigationParentItem` | `?string` | Nest under another item |