<?php

namespace App\Actions\StockTransfers;

use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/**
 * "Transfer now" (session 13): ship and receive in one step on the
 * transfer date, for moving stock across town. All or nothing.
 */
class TransferNow
{
    public function __construct(private ShipStockTransfer $ship, private ReceiveStockTransfer $receive) {}

    public function handle(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $shipped = $this->ship->handle($transfer);

            return $this->receive->handle($shipped, [], ReceiveStockTransfer::RETURN, $shipped->transfer_date);
        });
    }
}
