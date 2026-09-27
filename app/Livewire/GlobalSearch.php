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

            $results['customers'] = Customer::where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->limit(5)
                ->get(['id', 'name', 'email']);

            $results['vendors'] = Vendor::where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->limit(5)
                ->get(['id', 'name', 'email']);

            $results['invoices'] = Invoice::where('invoice_number', 'like', "%{$search}%")
                ->limit(5)
                ->get(['id', 'invoice_number', 'status', 'total']);

            $results['bills'] = Bill::where('bill_number', 'like', "%{$search}%")
                ->limit(5)
                ->get(['id', 'bill_number', 'status', 'total']);

            $results['items'] = Item::where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->limit(5)
                ->get(['id', 'name', 'sku']);

            $results['employees'] = Employee::where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'employee_id']);

            // Filter out empty collections
            $results = array_filter($results, fn($collection) => $collection->isNotEmpty());
        }

        return view('livewire.global-search', ['results' => $results]);
    }
}
