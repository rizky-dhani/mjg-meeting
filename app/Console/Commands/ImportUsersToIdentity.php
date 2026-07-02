<?php

namespace App\Console\Commands;

use App\Models\Identity\Company;
use App\Models\Identity\Department as IdentityDepartment;
use App\Models\Identity\Designation;
use App\Models\Identity\User as IdentityUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportUsersToIdentity extends Command
{
    protected $signature = 'app:import-users-to-identity
        {company_id? : Override MEDQUEST_USERS_COMPANY_ID}
        {--dry-run : Preview changes without writing}';

    protected $description = 'Migrate existing local users to centralized identity database';

    public function handle(): int
    {
        $companyId = $this->argument('company_id') ?: env('MEDQUEST_USERS_COMPANY_ID');
        if (! $companyId) {
            $this->error('Company ID required. Set MEDQUEST_USERS_COMPANY_ID env var or pass as argument.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $localUsers = DB::connection()->table('users')->get();
        $bar = $this->output->createProgressBar($localUsers->count());
        $bar->start();

        $inserted = 0;
        $matched = 0;
        $skipped = 0;

        foreach ($localUsers as $localUser) {
            // Step 1: Find or create identity user by email
            $identityUser = IdentityUser::where('email', $localUser->email)->first();

            if ($identityUser) {
                $this->line("  Matched: {$localUser->email} → {$identityUser->userId}");
                $matched++;
            } else {
                // Check for duplicate emails within the import batch
                $dupEmailBatch = $localUsers->where('email', $localUser->email)->where('id', '!=', $localUser->id);
                if ($dupEmailBatch->isNotEmpty()) {
                    $this->warn("  Skipped (duplicate email): {$localUser->email}");
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Map department
                $departmentId = $this->resolveDepartment($localUser, $companyId);

                // Map designation from position column
                $designationId = $this->resolveDesignation($localUser->position ?? 'Staff');

                if ($dryRun) {
                    $this->line("  Would create: {$localUser->email}");
                } else {
                    $identityUser = IdentityUser::create([
                        'company_id' => $companyId,
                        'employee_code' => $localUser->employee_number ?? 'EMP-' . Str::random(6),
                        'name' => $localUser->name,
                        'email' => $localUser->email,
                        'initial' => isset($localUser->initials) ? substr($localUser->initials, 0, 4) : substr($localUser->name, 0, 2),
                        'department_id' => $departmentId,
                        'designation_id' => $designationId,
                        'is_active' => true,
                        'email_verified_at' => $localUser->email_verified_at,
                    ]);
                }
                $inserted++;
            }

            if (! $dryRun && $identityUser) {
                // Update local user with identity userId
                User::where('id', $localUser->id)->update([
                    'user_id' => $identityUser->userId,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$inserted} created, {$matched} matched, {$skipped} skipped.");

        if (! $dryRun) {
            $this->warn('Run the conversion migration to drop old profile columns: php artisan migrate');
        }

        return self::SUCCESS;
    }

    protected function resolveDepartment(object $localUser, string $companyId): string
    {
        // If local user has department_id, try to map by name from the local departments table
        if (isset($localUser->department_id) && $localUser->department_id) {
            $localDept = DB::connection()->table('departments')->find($localUser->department_id);
            if ($localDept) {
                $identityDept = IdentityDepartment::where('company_id', $companyId)
                    ->where('name', $localDept->name)
                    ->first();
                if ($identityDept) {
                    return $identityDept->departmentId;
                }

                // Create department in identity DB
                return IdentityDepartment::create([
                    'company_id' => $companyId,
                    'name' => $localDept->name,
                ])->departmentId;
            }
        }

        // Fallback: create or find a "General" department
        $general = IdentityDepartment::firstOrCreate(
            ['company_id' => $companyId, 'name' => 'General'],
        );

        return $general->departmentId;
    }

    protected function resolveDesignation(string $name): string
    {
        return Designation::firstOrCreate(
            ['name' => $name],
        )->designationId;
    }
}
