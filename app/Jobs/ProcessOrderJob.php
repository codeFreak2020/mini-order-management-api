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

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly Order $order)
    {
        //
    }

    /**
     * Execute the job: finalise the order and notify the customer.
     */
    public function handle(): void
    {
        $this->order->update(['status' => Order::STATUS_COMPLETED]);

        $this->order->loadMissing('user', 'items');

        $this->order->user->notify(new OrderPlaced($this->order));
    }
}
