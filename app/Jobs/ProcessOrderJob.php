<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderPlaced;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   
    public int $tries = 3;
    public function __construct(public readonly Order $order)
    {
        //
    }
    public function handle(): void
    {
        $this->order->update(['status' => Order::STATUS_COMPLETED]);

        $this->order->loadMissing('user', 'items');

        $this->order->user->notify(new OrderPlaced($this->order));
    }
}
