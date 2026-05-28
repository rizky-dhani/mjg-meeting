<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GeneratePermissionsAction
{
    private const MODEL_NAMESPACE = 'App\\Models\\';

    private const EXCLUDED_MODELS = [
        'Permission',
        'Role',
        'User',
    ];

    public static function make(): Action
    {
        return Action::make('generatePermissions')
            ->label('Generate Permissions')
            ->icon('heroicon-o-bolt')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Generate Model Permissions')
            ->modalDescription('This will generate CRUD permissions for all models. Existing permissions will be skipped.')
            ->action(fn () => static::generate());
    }

    protected static function generate(): string
    {
        $guard = config('auth.defaults.guard', 'web');
        $created = 0;
        $skipped = 0;

        $models = static::discoverModels();

        $actions = ['view_any', 'view', 'create', 'update', 'delete'];

        foreach ($models as $model) {
            $name = class_basename($model);
            $slug = Str::snake($name);

            foreach ($actions as $action) {
                $permissionName = "{$action}_{$slug}";

                $exists = \App\Models\Permission::where('name', $permissionName)
                    ->where('guard_name', $guard)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                \App\Models\Permission::create([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);

                $created++;
            }
        }

        if ($created === 0 && $skipped === 0) {
            return 'No models found to generate permissions for.';
        }

        return "Created {$created} permissions, skipped {$skipped} existing.";
    }

    protected static function discoverModels(): array
    {
        $path = app_path('Models');
        $filesystem = new Filesystem;
        $models = [];

        foreach ($filesystem->files($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = static::MODEL_NAMESPACE . $file->getFilenameWithoutExtension();

            if (! class_exists($class)) {
                continue;
            }

            if (! is_a($class, Model::class, true)) {
                continue;
            }

            $shortName = class_basename($class);

            if (in_array($shortName, static::EXCLUDED_MODELS, true)) {
                continue;
            }

            $models[] = $class;
        }

        sort($models);

        return $models;
    }
}
