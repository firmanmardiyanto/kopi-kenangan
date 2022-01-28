<?php

namespace App\Mail;

use App\Models\Buyer;
use App\Models\Order;
use Exception;
use Illuminate\Mail\Mailable;

class OrderCreated extends Mailable
{
    public $order, $totalPrice;
    public function __construct(Order $order, $totalPrice)
    {
        $this->order = $order;
        $this->totalPrice = $totalPrice;
    }
    public function build()
    {
        return $this->markdown('emails.orders.created', [
            'order' => $this->order,
            'totalPrice' => $this->totalPrice,
        ])->subject('Order Created')
        ->to($this->order->user->email)
        ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
    }
}