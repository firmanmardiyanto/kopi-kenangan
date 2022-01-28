<?php

namespace App\Http\Controllers;

use App\Mail\OrderCreated;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::all();
        $orders->transform(function ($order) {
            $order->total_price = $order->orderProducts->sum(function ($orderProduct) use ($order) {
                $totalPrice = $orderProduct->product->price * $orderProduct->lots;
                return $totalPrice - $order->voucher->subtractor;
            });
            return $order;
        });

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'data' => ['orders' => $orders]
        ], 200);
    }

    public function show($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }
        $order->total_price = $order->orderProducts->sum(function ($orderProduct) use ($order) {
            $totalPrice = $orderProduct->product->price * $orderProduct->lots;
            return $totalPrice - $order->voucher->subtractor;
        });

        return response()->json([
            'message' => 'Order retrieved successfully',
            'data' => ['order' => $order]
        ], 200);
    }

    public function getOrdersByBuyer($buyerId)
    {
        $orders = Order::where('buyer_user_id', $buyerId)->get();
        $orders->transform(function ($order) {
            $order->total_price = $order->orderProducts->sum(function ($orderProduct) use ($order) {
                $totalPrice = $orderProduct->product->price * $orderProduct->lots;
                return $totalPrice - $order->voucher->subtractor;
            });
            return $order;
        });

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'data' => ['orders' => $orders]
        ], 200);
    }

    function getOrdersByUserLogin()
    {
        $user = Auth::user();
        if ($user->buyer) {
            return $this->getOrdersByBuyer($user->id);
        } else {
            throw new AuthorizationException('User is not a buyer');
        }
    }

    public function addOrderByUser(Request $request)
    {
        $request = json_decode($request->getContent(), true);

        $validationRules = [
            'voucher_id' => 'exists:vouchers,id',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric',
        ];
        Validator::make($request, $validationRules);
        $request = (object) $request;
        try {
            if (!$buyer = Auth::user()->buyer)
                throw new AuthorizationException('User is not a buyer');
            DB::beginTransaction();
            $order = new Order();
            $order->buyer_user_id = $buyer->user->id;
            $order->voucher_id = $request->voucher_id;
            $order->save();

            foreach ($request->products as $product) {
                $order->orderProducts()->create([
                    'product_id' => $product['id'],
                    'lots' => $product['quantity']
                ]);
            }

            $totalPrice = 0;
            foreach ($order->orderProducts as $orderProduct) {
                $totalPrice += $orderProduct->product->price * $orderProduct->lots;
            }
            $totalPrice -= $order->voucher->subtractor;
            if ($totalPrice == 0)
                $totalPrice = 0;

            DB::commit();
            
            Mail::send(new OrderCreated($order, $totalPrice));
            
            if (Mail::failures()) {
                return response()->json([
                    'message' => 'Order created successfully without email sending',
                    'data' => ['order' => $order]
                ], 200);
            }

            return response()->json([
                'message' => 'Order created successfully with email sent',
                'data' => [
                    'total_price' => $totalPrice,
                    'order' => $order,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteOrderByUser($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }
        if (!$order->buyer_user_id == Auth::user()->id) {
            throw new AuthorizationException('User is not a buyer');
        }
        try {
            DB::beginTransaction();
            $order->orderProducts()->delete();
            $order->delete();
            DB::commit();
            return response()->json([
                'message' => 'Order deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order could not be deleted'
            ], 500);
        }
    }
}
