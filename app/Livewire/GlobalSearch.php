<?php

namespace App\Livewire;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Vendor;
use Livewire\Component;

class GlobalSearch extends Component
{
    public $query = '';
    public $isOpen = false;

    public function updatedQuery()
    {
        $this->isOpen = strlen($this->query) >= 2;
    }

    public function selectResult()
    {
        $this->reset(['query', 'isOpen']);
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function render()
    {
        $results = [];

        if (strlen($this->query) >= 2) {
            $search = $this->query;
            $user = auth()->user();

            // Only search what the user is allowed to see (finding L12).
            $sections = [
                'customers' => ['view customers', fn () => Customer::where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%"))
                    ->limit(5)->get(['id', 'name', 'email'])],
                'vendors' => ['view vendors', fn () => Vendor::where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%"))
                    ->limit(5)->get(['id', 'name', 'email'])],
                'invoices' => ['view invoices', fn () => Invoice::where('invoice_number', 'like', "%{$search}%")
                    ->limit(5)->get(['id', 'invoice_number', 'status', 'total'])],
                'bills' => ['view bills', fn () => Bill::where('bill_number', 'like', "%{$search}%")
                    ->limit(5)->get(['id', 'bill_number', 'status', 'total'])],
                'items' => ['view items', fn () => Item::where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))
                    ->limit(5)->get(['id', 'name', 'sku'])],
                'employees' => ['view employees', fn () => Employee::where(fn ($q) => $q
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%"))
                    ->limit(5)->get(['id', 'first_name', 'last_name', 'employee_id'])],
            ];

            foreach ($sections as $key => [$permission, $query]) {
                if ($user?->can($permission)) {
                    $results[$key] = $query();
                }
            }

            // Filter out empty collections
            $results = array_filter($results, fn ($collection) => $collection->isNotEmpty());
        }

        return view('livewire.global-search', ['results' => $results]);
    }
}
