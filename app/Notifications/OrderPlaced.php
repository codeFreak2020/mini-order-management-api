<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public readonly Order $order)
    {
        //
    }
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Confirmation — '.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your order **'.$this->order->order_number.'** has been placed successfully.')
            ->line('Total amount: **$'.number_format((float) $this->order->total_price, 2).'**')
            ->line('Order status: '.ucfirst($this->order->status))
            ->line('Thank you for shopping with us!');
    }
}
