<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesReceipt;
use App\Models\SalesReceiptItem;

/**
 * An edit writes an invoice's or sales receipt's lines again, which drops
 * the cost each stock line was issued at. With no cost on a line,
 * JournalService::documentCogs puts all the document's stock back and
 * issues it again at today's cost: changing only the notes on a 5 October
 * sale costed it with a purchase made on the 10th, and (since audit MB-15)
 * reversed and re-posted its journal to say so.
 *
 * When the stock lines are the same (item and quantity, from the same
 * warehouse), the new lines keep the costs the old ones were issued at.
 * Any change to them still re-issues the whole document, as before.
 */
trait KeepsStockCosts
{
    /**
     * The stock lines' costs before the edit, by item and quantity; null
     * when a stock line has none yet (nothing to keep).
     *
     * @return array<string, list<string>>|null
     */
    protected function stockCosts(Invoice|SalesReceipt $document): ?array
    {
        $costs = [];
        foreach ($this->stockLines($document) as $line) {
            if ($line->unit_cost === null) {
                return null;
            }
            $costs[$this->stockKey($line)][] = (string) $line->unit_cost;
        }

        return $costs;
    }

    /** @param  array<string, list<string>>|null  $before  from stockCosts() */
    protected function keepStockCosts(Invoice|SalesReceipt $document, ?array $before, bool $sameWarehouse): void
    {
        if (! $before || ! $sameWarehouse) {
            return;
        }

        $after = [];
        foreach ($this->stockLines($document) as $line) {
            $after[$this->stockKey($line)][] = $line;
        }
        $counts = fn (array $lines) => array_map('count', $lines);
        $was = $counts($before);
        $now = $counts($after);
        ksort($was);
        ksort($now);
        if ($was !== $now) {
            return;
        }

        foreach ($after as $key => $lines) {
            foreach ($lines as $i => $line) {
                $line->forceFill(['unit_cost' => $before[$key][$i]])->saveQuietly();
            }
        }
    }

    /**
     * The lines that move stock, as JournalService::documentCogs counts them.
     *
     * @return list<InvoiceItem|SalesReceiptItem>
     */
    private function stockLines(Invoice|SalesReceipt $document): array
    {
        return $document->items()->with('item')->orderBy('id')->get()
            ->filter(fn ($line) => $line->item && $line->item->track_inventory && (float) $line->quantity > 0)
            ->values()->all();
    }

    private function stockKey(InvoiceItem|SalesReceiptItem $line): string
    {
        return $line->item_id.':'.number_format((float) $line->quantity, 4, '.', '');
    }
}
