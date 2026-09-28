<?php

namespace App\Livewire\Traits;

trait WithBulkOperations
{
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $bulkSuccessMessage = '';

    public $bulkErrorMessage = '';

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredItemIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredItemIds());
    }

    public function resetBulkSelection()
    {
        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function clearBulkMessages()
    {
        $this->bulkSuccessMessage = '';
        $this->bulkErrorMessage = '';
    }

    /**
     * Override this method in the component to return the filtered item IDs
     */
    abstract protected function getFilteredItemIds(): array;

    /**
     * Override this method in the component to handle bulk actions
     */
    abstract public function applyBulkAction();
}
