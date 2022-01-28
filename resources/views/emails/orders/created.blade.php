@component('mail::message')
# Order Created

Your order has been created!

Your order id is: OKK-{{ $order->id }}

List of products:
@foreach ($order->orderProducts as $orderProduct)
- {{ $orderProduct->product->name }} ({{ $orderProduct->lots }} units x Rp.{{ number_format($orderProduct->product->price, 0, '', '.') }})
@endforeach

Discount: Rp.{{ number_format($order->voucher->subtractor, 0, '', '.') }}

Total Price: Rp.{{ number_format($totalPrice, 0, '', '.') }}

@component('mail::button', ['url' => config('app.url').'/admin/orders/'.$order->id])
View Order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent