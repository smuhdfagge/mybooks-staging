<?php

namespace App\Actions\Assembly;

use App\Models\BillOfMaterial;
use App\Models\BomCost;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Services\AccountCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating or changing a bill of materials (session 14).
 *
 * - The finished item and every component must keep stock (the old code
 *   took services and non-stock items, which then had no cost to move).
 * - An item can't be its own component, directly or through other bills
 *   (sub-assemblies are fine, loops are not). The old code only checked the
 *   direct case.
 * - The same component on two lines goes on one line.
 * - Extra costs need a description and an amount; the account credited
 *   defaults to Production Costs Applied and can't be Inventory itself.
 *
 * Changing a bill doesn't change orders already made from it: they keep
 * their own lines.
 *
 * $data keys: item_id, name, version, description, output_quantity,
 * is_active, components[] (item_id, quantity, waste_percentage, notes),
 * costs[] (description, amount, account_id).
 */
class SaveBillOfMaterial
{
    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): BillOfMaterial
    {
        [$item, $components, $costs] = $this->check($tenantId, $data);

        return DB::transaction(function () use ($tenantId, $data, $item, $components, $costs) {
            $bom = new BillOfMaterial([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'name' => trim((string) $data['name']),
                'version' => $data['version'] ?? null,
                'description' => $data['description'] ?? null,
                'output_quantity' => round((float) $data['output_quantity'], 4),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
            $bom->skipTenantGuard = true;
            $bom->save();
            $this->saveLines($bom, $components, $costs);

            return $bom->fresh(['components', 'costs']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(BillOfMaterial $bom, array $data): BillOfMaterial
    {
        [$item, $components, $costs] = $this->check((int) $bom->tenant_id, $data, $bom);

        return DB::transaction(function () use ($bom, $data, $item, $components, $costs) {
            $bom->update([
                'item_id' => $item->id,
                'name' => trim((string) $data['name']),
                'version' => $data['version'] ?? null,
                'description' => $data['description'] ?? null,
                'output_quantity' => round((float) $data['output_quantity'], 4),
                // A form leaves an unticked box out: that means off. The old
                // code read a missing box as "on", so a bill couldn't be switched off.
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $bom->components()->delete();
            $bom->costs()->delete();
            $this->saveLines($bom, $components, $costs);

            return $bom->fresh(['components', 'costs']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<int, array<string, mixed>>  $costs
     */
    private function saveLines(BillOfMaterial $bom, array $components, array $costs): void
    {
        $bom->components()->createMany($components);
        foreach ($costs as $cost) {
            $row = new BomCost($cost + ['tenant_id' => $bom->tenant_id, 'bill_of_materials_id' => $bom->id]);
            $row->skipTenantGuard = true;
            $row->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Item, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function check(int $tenantId, array $data, ?BillOfMaterial $bom = null): array
    {
        $item = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) ($data['item_id'] ?? 0))->first();
        if (! $item) {
            throw ValidationException::withMessages(['item_id' => 'Choose the item this makes.']);
        }
        if (! $item->track_inventory) {
            throw ValidationException::withMessages(['item_id' => "{$item->name} doesn't keep stock, so it can't be made here. Switch on \"Track stock\" for it first."]);
        }
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw ValidationException::withMessages(['name' => 'Give the bill a name.']);
        }
        if ((float) ($data['output_quantity'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['output_quantity' => 'Enter how many one batch makes.']);
        }

        $components = [];
        foreach (array_values($data['components'] ?? []) as $index => $line) {
            $quantity = round((float) ($line['quantity'] ?? 0), 4);
            if (empty($line['item_id']) && $quantity <= 0) {
                continue; // empty row on the form
            }
            $part = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) ($line['item_id'] ?? 0))->first();
            if (! $part) {
                throw ValidationException::withMessages(["components.{$index}.item_id" => 'Choose an item.']);
            }
            if (! $part->track_inventory) {
                throw ValidationException::withMessages(["components.{$index}.item_id" => "{$part->name} doesn't keep stock, so it can't be a component. Put costs like labour under extra costs instead."]);
            }
            if ($part->id === $item->id) {
                throw ValidationException::withMessages(["components.{$index}.item_id" => "{$item->name} can't be made from itself."]);
            }
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["components.{$index}.quantity" => "Enter how much {$part->name} one batch uses."]);
            }
            $waste = round((float) ($line['waste_percentage'] ?? 0), 2);
            if ($waste < 0 || $waste > 100) {
                throw ValidationException::withMessages(["components.{$index}.waste_percentage" => 'Wastage must be between 0 and 100%.']);
            }

            if (isset($components[$part->id])) {
                $components[$part->id]['quantity'] = round($components[$part->id]['quantity'] + $quantity, 4);
            } else {
                $components[$part->id] = ['item_id' => $part->id, 'quantity' => $quantity, 'waste_percentage' => $waste, 'notes' => $line['notes'] ?? null];
            }
        }
        if ($components === []) {
            throw ValidationException::withMessages(['components' => 'Add at least one component.']);
        }

        if ($loop = BillOfMaterial::loopingComponent($tenantId, $item->id, array_keys($components), $bom?->id)) {
            $name = Item::withoutGlobalScopes()->whereKey($loop)->value('name');
            $index = array_search($loop, array_keys($components), true);
            throw ValidationException::withMessages(["components.{$index}.item_id" => "{$name} is itself made from {$item->name} (on another bill of materials), so it can't be one of its components."]);
        }

        $costs = [];
        $inventoryCode = AccountCodeService::resolve($tenantId, 'inventory');
        foreach (array_values($data['costs'] ?? []) as $index => $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($description === '' && $amount <= 0) {
                continue;
            }
            if ($description === '') {
                throw ValidationException::withMessages(["costs.{$index}.description" => 'Say what this cost is for (labour, power, packaging...).']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(["costs.{$index}.amount" => "Enter the {$description} cost per batch."]);
            }
            $account = ! empty($line['account_id'])
                ? ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->whereKey((int) $line['account_id'])->first()
                : self::defaultCostAccount($tenantId);
            if (! empty($line['account_id']) && ! $account) {
                throw ValidationException::withMessages(["costs.{$index}.account_id" => 'Choose one of your own accounts.']);
            }
            if ($account && $account->account_code === $inventoryCode) {
                throw ValidationException::withMessages(["costs.{$index}.account_id" => 'Choose the account the cost comes from (wages, bank, Production Costs Applied...), not Inventory.']);
            }
            $costs[] = ['description' => $description, 'amount' => $amount, 'account_id' => $account?->id];
        }

        return [$item, array_values($components), $costs];
    }

    /** Production Costs Applied, the usual account to credit extra costs to. */
    public static function defaultCostAccount(int $tenantId): ?ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('account_code', AccountCodeService::resolve($tenantId, 'production_costs_applied'))->first();
    }
}
