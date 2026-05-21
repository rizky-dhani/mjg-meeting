---
source: Context7 API
library: filament
package: filament/filament
topic: Navigation Groups
fetched: 2026-05-20
official_docs: https://filamentphp.com/docs/5.x/navigation
---

# Navigation Groups in Filament v5

## 1. Group Resources with `navigationGroup()` Property

Set the `$navigationGroup` static property on your resource to group navigation items:

```php
use UnitEnum;

protected static string | UnitEnum | null $navigationGroup = 'Shop';
```

You can use a string or a `UnitEnum` value. The enum approach is recommended for organization.

### Using an Enum for Navigation Groups

**Enum definition:**
```php
enum NavigationGroup
{
    case Shop;
    
    case Blog;
    
    case Settings;
}
```

**Resource usage:**
```php
protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Shop;
```

### Enum with Custom Labels

```php
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Shop;
    
    case Blog;
    
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Shop => __('navigation-groups.shop'),
            self::Blog => __('navigation-groups.blog'),
            self::Settings => __('navigation-groups.settings'),
        };
    }
}
```

### Enum with Icons and Labels

```php
use BackedEnum;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum NavigationGroup implements HasLabel, HasIcon
{
    case Shop;
    
    case Blog;
    
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Shop => __('navigation-groups.shop'),
            self::Blog => __('navigation-groups.blog'),
            self::Settings => __('navigation-groups.settings'),
        };
    }

    public function getIcon(): string | BackedEnum | Htmlable | null
    {
        return match ($this) {
            self::Shop => Heroicon::OutlinedShoppingCart,
            self::Blog => Heroicon::OutlinedPencil,
            self::Settings => Heroicon::OutlinedCog6Tooth,
        };
    }
}
```

## 2. Register Groups in Panel Provider

Configure navigation groups directly in the panel:

```php
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
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
                ->collapsed(),
        ]);
}
```

### Reorder Navigation Groups

Reorder groups by passing their labels in the desired order:

```php
$panel
    ->navigationGroups([
        'Shop',
        'Blog',
        'Settings',
    ])
```

### Group Ordering Methods

You can pass strings (label-based) or `NavigationGroup` objects with icons.

## 3. Group Order and Label Customization

### Static Property Approach

```php
protected static string | UnitEnum | null $navigationGroup = 'Settings';
```

### Dynamic Method Approach

```php
public static function getNavigationGroup(): ?string
{
    return __('filament/navigation.groups.shop');
}
```

## 4. Example: Multiple Resources in One Group

```php
// app/Filament/Resources/Shop/ProductResource.php
protected static string | UnitEnum | null $navigationGroup = 'Shop';

// app/Filament/Resources/Shop/OrderResource.php
protected static string | UnitEnum | null $navigationGroup = 'Shop';

// app/Filament/Resources/Shop/CustomerResource.php
protected static string | UnitEnum | null $navigationGroup = 'Shop';
```

Or with enums:

```php
// app/Enums/NavigationGroup.php
enum NavigationGroup: string
{
    case Shop = 'shop';
    case Blog = 'blog';
    case Settings = 'settings';
    
    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Shop',
            self::Blog => 'Blog',
            self::Settings => 'Settings',
        };
    }
}

// app/Filament/Resources/Shop/ProductResource.php
protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Shop;
```

## 5. Grouping Resource Navigation Items Under Parent Items

You may group navigation items as children of other items:

```php
protected static ?string $navigationParentItem = 'Products';

protected static string | UnitEnum | null $navigationGroup = 'Shop';
```

Or with the dynamic method:

```php
public static function getNavigationParentItem(): ?string
{
    return __('filament/navigation.groups.shop.items.products');
}
```