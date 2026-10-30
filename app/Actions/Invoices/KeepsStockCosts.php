<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesReceipt;
use App\Models\SalesReceiptItem;
use App\Services\StockValuationService;

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
 *
 * documentCogs only puts stock back when it has a stock line to issue
 * again, so an edit that leaves none (the stock line replaced by a
 * service, or its item no longer kept in stock) puts it back here.
 */
trait KeepsStockCosts
{
    /**
     * The stock lines' costs before the edit, by item and quantity; null
     * when there is nothing to keep: a stock line with no cost yet, or a
     * line issued from stock whose item no longer is a stock item (made a
     * service, tracking turned off, deleted). That one's stock has to go
     * back, and only a full re-issue does that.
     *
     * @return array<string, list<string>>|null
     */
    protected function stockCosts(Invoice|SalesReceipt $document): ?array
    {
        $stock = $this->stockLines($document);
        $issued = $document->items()->whereNotNull('unit_cost')->count();
        if ($issued !== count(array_filter($stock, fn ($line) => $line->unit_cost !== null))) {
            return null;
        }

        $costs = [];
        foreach ($stock as $line) {
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
        $stock = $this->stockLines($document);
        if ($stock === []) {
            // A no-op when the document never took any.
            app(StockValuationService::class)->returnStock($document::class, $document->id);

            return;
        }

        if (! $before || ! $sameWarehouse) {
            return;
        }

        $after = [];
        foreach ($stock as $line) {
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
        return array_values($document->items()->with('item')->orderBy('id')->get()
            ->filter(fn ($line) => $line->item && $line->item->track_inventory && (float) $line->quantity > 0)
            ->all());
    }

    private function stockKey(InvoiceItem|SalesReceiptItem $line): string
    {
        return $line->item_id.':'.number_format((float) $line->quantity, 4, '.', '');
    }
}
