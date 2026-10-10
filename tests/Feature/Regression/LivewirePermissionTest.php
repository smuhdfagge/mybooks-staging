<?php

namespace Tests\Feature\Regression;

use App\Livewire\ActivityLogs\ActivityLogsTable;
use App\Livewire\Auth\RegisterWizard;
use App\Livewire\Bills\BillsTable;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Customers\CustomersTable;
use App\Livewire\Expenses\ExpensesTable;
use App\Livewire\Invoices\InvoicesTable;
use App\Livewire\Items\ItemCategoriesTable;
use App\Livewire\Items\ItemsTable;
use App\Livewire\TaxGroups\TaxGroupsTable;
use App\Livewire\TaxRates\TaxRatesTable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\TaxGroup;
use App\Models\TaxRate;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Finding C2: Livewire actions must check permissions themselves, because
 * they don't go through the page route's permission middleware.
 */
class LivewirePermissionTest extends TestCase
{
    /**
     * Public methods that only change what is on screen (filters, modals,
     * paging, the registration wizard). Everything else must call
     * requirePermission(), requireAdmin() or authorizeBulkAction().
     */
    private const UI_ONLY_METHODS = [
        'cancelDelete', 'clearFilters', 'close', 'closeCancelModal', 'closeDisposeModal', 'closeRejectModal',
        'closeUpgradeModal', 'getSettingsArray', 'goToStep', 'nextStep', 'openCancelModal',
        'openUpgradeModal', 'placeholder', 'previousStep', 'register', 'resetToDefaults', 'selectResult',
        'selectPage', 'selectAllMatching', 'clearSelection',
        'submit', 'toggleType',
    ];

    private const UI_ONLY_PREFIXES = [
        'render', 'mount', 'updating', 'updated', 'sortBy', 'getPage', 'gotoPage', 'nextPage',
        'previousPage', 'setPage', 'resetPage', 'queryString', 'boot', 'hydrate', 'dehydrate',
    ];

    /** Components that take no part in the check (registration happens before login). */
    private const SKIP_COMPONENTS = [RegisterWizard::class];

    /** @return array<class-string<Component>> */
    private function components(): array
    {
        $classes = [];
        foreach ((new Finder)->files()->in(app_path('Livewire'))->name('*.php') as $file) {
            $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Component::class)) {
                continue;
            }
            $classes[] = $class;
        }
        sort($classes);

        return $classes;
    }

    private function bulkMap(string $class): array
    {
        $method = new ReflectionMethod($class, 'bulkActionPermissions');

        return $method->invoke(new $class);
    }

    public function test_every_public_livewire_action_checks_a_permission(): void
    {
        $unchecked = [];

        foreach ($this->components() as $class) {
            if (in_array($class, self::SKIP_COMPONENTS, true)) {
                continue;
            }
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || ! str_starts_with($method->getDeclaringClass()->getName(), 'App\\')) {
                    continue;
                }
                $name = $method->getName();
                if (in_array($name, self::UI_ONLY_METHODS, true) || str_starts_with($name, 'get') && str_ends_with($name, 'Property')) {
                    continue;
                }
                foreach (self::UI_ONLY_PREFIXES as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        continue 2;
                    }
                }
                $lines = file($method->getFileName());
                $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
                if (! preg_match('/requirePermission|requireAdmin|authorizeBulkAction/', $body)) {
                    $unchecked[] = class_basename($class).'::'.$name;
                }
            }
        }

        $this->assertSame([], $unchecked,
            'These Livewire actions do not check permissions. Add a check, or add the method to UI_ONLY_METHODS if it only changes the screen.');
    }

    public function test_every_bulk_action_component_uses_the_permission_map(): void
    {
        foreach ($this->components() as $class) {
            if (! method_exists($class, 'applyBulkAction')) {
                continue;
            }
            $this->assertContains(ChecksPermissions::class, class_uses($class), class_basename($class));
            $this->assertNotEmpty($this->bulkMap($class), class_basename($class).' has no bulk permissions');
        }
    }

    public function test_bulk_actions_are_refused_without_permission_and_allowed_with_it(): void
    {
        $this->createAuthenticatedUser();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $checked = 0;

        foreach ($this->components() as $class) {
            if (! method_exists($class, 'applyBulkAction')) {
                continue;
            }

            foreach ($this->bulkMap($class) as $action => $permission) {
                // No permissions: refused
                $this->user->syncPermissions([]);
                $this->user->syncRoles([]);
                $this->refreshPermissions();

                Livewire::test($class)
                    ->set('selectedItems', ['999999'])
                    ->set('bulkAction', $action)
                    ->call('applyBulkAction')
                    ->assertForbidden();

                // With the permission (or admin role): not refused
                if ($permission === $class::ADMIN_ONLY) {
                    $this->user->assignRole('admin');
                } else {
                    Permission::findOrCreate($permission, 'web');
                    $this->user->givePermissionTo($permission);
                }
                $this->refreshPermissions();

                Livewire::test($class)
                    ->set('selectedItems', ['999999'])
                    ->set('bulkAction', $action)
                    ->call('applyBulkAction')
                    ->assertStatus(200);

                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(69, $checked, 'Expected to check every bulk action');
    }

    public function test_unknown_bulk_action_is_refused(): void
    {
        $this->createSuperAdmin();

        Livewire::test(CustomersTable::class)
            ->set('selectedItems', ['1'])
            ->set('bulkAction', 'something_new')
            ->call('applyBulkAction')
            ->assertForbidden();
    }

    /**
     * Row-level buttons (delete one, approve one, toggle active, ...).
     */
    public function test_row_actions_are_refused_without_permission(): void
    {
        $this->createAuthenticatedUser();
        $tenantId = $this->tenant->id;

        $taxRate = TaxRate::create(['tenant_id' => $tenantId, 'name' => 'VAT', 'code' => 'VAT', 'rate' => 7.5, 'type' => 'exclusive', 'is_active' => true]);
        $taxGroup = TaxGroup::create(['tenant_id' => $tenantId, 'name' => 'Group', 'code' => 'GRP', 'is_active' => true]);
        $category = ItemCategory::create(['tenant_id' => $tenantId, 'name' => 'Cat', 'is_active' => true]);
        $customer = Customer::factory()->create(['tenant_id' => $tenantId]);
        $item = Item::factory()->create(['tenant_id' => $tenantId]);
        $expense = Expense::factory()->create(['tenant_id' => $tenantId]);

        $cases = [
            [CustomersTable::class, 'deleteCustomer', [$customer->id]],
            [ItemsTable::class, 'deleteOne', [$item->id]],
            [ItemCategoriesTable::class, 'deleteOne', [$category->id]],
            [ItemCategoriesTable::class, 'toggleActive', [$category->id]],
            [TaxRatesTable::class, 'toggleActive', [$taxRate->id]],
            [TaxRatesTable::class, 'toggleDefault', [$taxRate->id]],
            [TaxGroupsTable::class, 'toggleActive', [$taxGroup->id]],
            [ExpensesTable::class, 'deleteOne', [$expense->id]],
            [ExpensesTable::class, 'submitForApproval', [$expense->id]],
            [ExpensesTable::class, 'approveExpense', [$expense->id]],
            [ExpensesTable::class, 'markAsPaid', [$expense->id]],
            [BillsTable::class, 'deleteOne', [999999]],
            [ActivityLogsTable::class, 'export', ['csv']],
        ];

        foreach ($cases as [$class, $method, $args]) {
            Livewire::test($class)->call($method, ...$args)->assertForbidden();
        }

        // Nothing was changed
        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($item->fresh());
        $this->assertTrue((bool) $category->fresh()->is_active);
        $this->assertTrue((bool) $taxRate->fresh()->is_active);
        $this->assertTrue((bool) $taxGroup->fresh()->is_active);
    }

    public function test_row_actions_work_with_permission(): void
    {
        $this->createAuthenticatedUser(['delete customers', 'edit items']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $category = ItemCategory::create(['tenant_id' => $this->tenant->id, 'name' => 'Cat', 'is_active' => true]);

        Livewire::test(CustomersTable::class)->call('deleteCustomer', $customer->id)->assertStatus(200);
        Livewire::test(ItemCategoriesTable::class)->call('toggleActive', $category->id)->assertStatus(200);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertFalse((bool) $category->fresh()->is_active);
    }

    /**
     * The assessment's original case: a view-only user could delete invoices
     * and mark them paid.
     */
    public function test_view_only_user_cannot_bulk_delete_or_mark_invoices_paid(): void
    {
        $this->createAuthenticatedUser(['view invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000900',
        ]);

        foreach (['mark_paid', 'delete'] as $action) {
            Livewire::test(InvoicesTable::class)
                ->set('selectedItems', [(string) $invoice->id])
                ->set('bulkAction', $action)
                ->call('applyBulkAction')
                ->assertForbidden();
        }

        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->deleted_at);
    }

    private function refreshPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
