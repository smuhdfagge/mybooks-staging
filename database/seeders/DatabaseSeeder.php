<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChartOfAccountService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create subscription plans first (required for registration)
        $this->call(PlanSeeder::class);

        // Create permissions
        $this->createPermissions();

        // Create roles
        $this->createRoles();

        // Demo and super-admin accounts all use the password "password", so
        // they are only created on developer machines and in tests (M8).
        // On a server, create the platform admin with AdminUserSeeder:
        //   ADMIN_EMAIL=... ADMIN_PASSWORD=... php artisan db:seed --class=AdminUserSeeder
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping demo and super-admin accounts outside local/testing.');

            return;
        }

        // Create Super Admin
        $this->createSuperAdmin();

        // Create Admin Panel Users
        $this->createAdminPanelUsers();

        // Create Demo Tenant with sample data
        $this->createDemoTenant();
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Dashboard
            'view dashboard',

            // Dashboard Widgets
            'total-revenue dashboard-widgets',
            'outstanding-receivables dashboard-widgets',
            'monthly-expenses dashboard-widgets',
            'employees-count dashboard-widgets',
            'quick-actions dashboard-widgets',
            'revenue-chart dashboard-widgets',
            'recent-invoices dashboard-widgets',
            'pending-bills dashboard-widgets',
            'low-stock dashboard-widgets',

            // Items
            'view items', 'create items', 'edit items', 'delete items',
            'view inventory', 'adjust inventory',

            // Sales
            'view customers', 'create customers', 'edit customers', 'delete customers',
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'send invoices',
            'view sales-orders', 'create sales-orders', 'edit sales-orders', 'delete sales-orders',
            'view sales-receipts', 'create sales-receipts', 'edit sales-receipts', 'delete sales-receipts',
            'view payments-received', 'create payments-received', 'edit payments-received', 'delete payments-received',

            // Purchases
            'view vendors', 'create vendors', 'edit vendors', 'delete vendors',
            'view expenses', 'create expenses', 'edit expenses', 'delete expenses',
            'view bills', 'create bills', 'edit bills', 'delete bills',
            'view purchase-orders', 'create purchase-orders', 'edit purchase-orders', 'delete purchase-orders',
            'view recurrent-bills', 'create recurrent-bills', 'edit recurrent-bills', 'delete recurrent-bills',
            'view recurrent-expenses', 'create recurrent-expenses', 'edit recurrent-expenses', 'delete recurrent-expenses',
            'view payments-made', 'create payments-made', 'edit payments-made', 'delete payments-made',

            // HR
            'view departments', 'create departments', 'edit departments', 'delete departments',
            'view designations', 'create designations', 'edit designations', 'delete designations',
            'view employees', 'create employees', 'edit employees', 'delete employees',
            'view leave-types', 'create leave-types', 'edit leave-types', 'delete leave-types',
            'view leaves', 'create leaves', 'edit leaves', 'delete leaves', 'approve leaves',
            'view payroll', 'create payroll', 'edit payroll', 'delete payroll', 'approve payroll',

            // Accountant
            'view chart-of-accounts', 'create chart-of-accounts', 'edit chart-of-accounts', 'delete chart-of-accounts',
            'view journals', 'create journals', 'edit journals', 'delete journals', 'post journals',
            'view banks', 'create banks', 'edit banks', 'delete banks', 'reconcile banks',
            'view withholding-tax', 'manage withholding-tax', 'remit withholding-tax',
            'view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules', 'delete accrual-schedules',
            'manage lock-dates', 'override lock-date',

            // Budgets
            'view budgets', 'create budgets', 'edit budgets', 'delete budgets',

            // Fixed Assets
            'view fixed-assets', 'create fixed-assets', 'edit fixed-assets', 'delete fixed-assets',
            'depreciate fixed-assets', 'dispose fixed-assets',
            'view fixed-asset-categories', 'create fixed-asset-categories', 'edit fixed-asset-categories', 'delete fixed-asset-categories',

            // Reports
            'view reports', 'export reports', 'file vat-returns',

            // Settings
            'view settings', 'edit settings',
            'manage subscription',
            'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'create roles', 'edit roles', 'delete roles',

            // Activity Logs
            'view activity-logs',

            // Tax Configuration
            'view tax-rates', 'create tax-rates', 'edit tax-rates', 'delete tax-rates',

            // Data Export & Import
            'export data',
            'import data',

            // Tenant Management (Super Admin only)
            'manage tenants',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    private function createRoles(): void
    {
        // Super Admin - has all permissions including tenant management
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web', 'tenant_id' => null]);
        $superAdmin->syncPermissions(Permission::all());

        // Admin - full access within tenant (excluding tenant management)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
        $adminPermissions = Permission::where('name', '!=', 'manage tenants')->pluck('name')->toArray();
        $admin->syncPermissions($adminPermissions);

        // Accountant
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web', 'tenant_id' => null]);
        $accountant->syncPermissions([
            'view dashboard',
            'total-revenue dashboard-widgets', 'outstanding-receivables dashboard-widgets',
            'monthly-expenses dashboard-widgets', 'revenue-chart dashboard-widgets',
            'recent-invoices dashboard-widgets', 'pending-bills dashboard-widgets',
            'low-stock dashboard-widgets',
            'view items', 'view inventory',
            'view customers', 'view invoices', 'create invoices', 'edit invoices', 'send invoices',
            'view sales-orders', 'view sales-receipts', 'create sales-receipts',
            'view payments-received', 'create payments-received',
            'view vendors', 'view expenses', 'create expenses', 'edit expenses',
            'view bills', 'create bills', 'edit bills',
            'view purchase-orders', 'create purchase-orders', 'edit purchase-orders',
            'view payments-made', 'create payments-made',
            'view chart-of-accounts', 'create chart-of-accounts', 'edit chart-of-accounts',
            'view journals', 'create journals', 'edit journals', 'post journals',
            'view banks', 'create banks', 'edit banks', 'reconcile banks',
            'view withholding-tax', 'manage withholding-tax', 'remit withholding-tax',
            'view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules',
            'view fixed-assets', 'create fixed-assets', 'edit fixed-assets', 'depreciate fixed-assets',
            'view reports', 'export reports', 'file vat-returns',
            'export data', 'import data',
            'override lock-date',
        ]);

        // Sales
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web', 'tenant_id' => null]);
        $sales->syncPermissions([
            'view dashboard',
            'total-revenue dashboard-widgets', 'outstanding-receivables dashboard-widgets',
            'quick-actions dashboard-widgets', 'recent-invoices dashboard-widgets',
            'view items',
            'view customers', 'create customers', 'edit customers',
            'view invoices', 'create invoices', 'edit invoices', 'send invoices',
            'view sales-orders', 'create sales-orders', 'edit sales-orders',
            'view sales-receipts', 'create sales-receipts',
            'view payments-received', 'create payments-received',
            'view reports',
        ]);

        // HR Manager
        $hr = Role::firstOrCreate(['name' => 'hr-manager', 'guard_name' => 'web', 'tenant_id' => null]);
        $hr->syncPermissions([
            'view dashboard',
            'employees-count dashboard-widgets', 'quick-actions dashboard-widgets',
            'view departments', 'create departments', 'edit departments',
            'view designations', 'create designations', 'edit designations',
            'view employees', 'create employees', 'edit employees',
            'view leave-types', 'create leave-types', 'edit leave-types',
            'view leaves', 'create leaves', 'edit leaves', 'approve leaves',
            'view payroll', 'create payroll', 'edit payroll', 'approve payroll',
            'view reports',
        ]);

        // Viewer
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web', 'tenant_id' => null]);
        $viewer->syncPermissions([
            'view dashboard',
            'total-revenue dashboard-widgets', 'outstanding-receivables dashboard-widgets',
            'monthly-expenses dashboard-widgets', 'employees-count dashboard-widgets',
            'revenue-chart dashboard-widgets', 'recent-invoices dashboard-widgets',
            'pending-bills dashboard-widgets', 'low-stock dashboard-widgets',
            'view items', 'view inventory',
            'view customers', 'view invoices', 'view sales-orders', 'view sales-receipts', 'view payments-received',
            'view vendors', 'view expenses', 'view bills', 'view purchase-orders', 'view payments-made',
            'view employees', 'view departments', 'view designations', 'view leaves', 'view payroll',
            'view chart-of-accounts', 'view journals',
            'view banks', 'view withholding-tax', 'view accrual-schedules',
            'view reports',
        ]);
    }

    private function createSuperAdmin(): void
    {
        // Main Super Admin
        $user = User::firstOrCreate(
            ['email' => 'admin@mybooks.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $user->forceFill(['is_super_admin' => true])->save();

        $user->assignRole('super-admin');

        // Tenant Manager Account
        $tenantManager = User::firstOrCreate(
            ['email' => 'manager@mybooks.local'],
            [
                'name' => 'Tenant Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $tenantManager->forceFill(['is_super_admin' => true])->save();

        $tenantManager->assignRole('super-admin');
    }

    private function createAdminPanelUsers(): void
    {
        AdminUser::firstOrCreate(
            ['email' => 'superadmin@mybooks.local'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => AdminUser::ROLE_SUPER_ADMIN,
            ]
        );
    }

    private function createDemoTenant(): void
    {
        // Create demo tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-company'],
            [
                'name' => 'Demo Company Ltd',
                'email' => 'info@democompany.com',
                'phone' => '+1234567890',
                'address' => '123 Business Street',
                'city' => 'Business City',
                'country' => 'Nigeria',
                'currency' => 'NGN',
                'timezone' => 'Africa/Lagos',
                'is_active' => true,
            ]
        );

        // Create tenant admin
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'demo@mybooks.local'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]
        );

        $tenantAdmin->assignRole('admin');

        // Default chart of accounts are now automatically created via Tenant model's booted method
        // For existing tenants or if the tenant was created with firstOrCreate, ensure accounts exist
        ChartOfAccountService::createDefaultAccounts($tenant->id);

        // Create default leave types
        $this->createLeaveTypes($tenant->id);
    }

    private function createLeaveTypes(int $tenantId): void
    {
        $leaveTypes = [
            ['name' => 'Annual Leave', 'days_per_year' => 20, 'is_paid' => true, 'description' => 'Paid vacation days'],
            ['name' => 'Sick Leave', 'days_per_year' => 10, 'is_paid' => true, 'description' => 'Paid sick days'],
            ['name' => 'Maternity Leave', 'days_per_year' => 90, 'is_paid' => true, 'description' => 'Maternity leave for expecting mothers'],
            ['name' => 'Paternity Leave', 'days_per_year' => 14, 'is_paid' => true, 'description' => 'Paternity leave for new fathers'],
            ['name' => 'Unpaid Leave', 'days_per_year' => 30, 'is_paid' => false, 'description' => 'Leave without pay'],
            ['name' => 'Bereavement Leave', 'days_per_year' => 5, 'is_paid' => true, 'description' => 'Leave for family bereavement'],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $leaveType['name']],
                array_merge($leaveType, ['tenant_id' => $tenantId, 'is_active' => true])
            );
        }
    }
}
