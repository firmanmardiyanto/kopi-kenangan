<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $fillable = ['buyer_user_id', 'lots', 'voucher_id'];

    function user() {
        return $this->belongsTo('App\Models\User', 'buyer_user_id', 'id');
    }

    function voucher() {
        return $this->belongsTo('App\Models\Voucher');
    }

    function orderProducts() {
        return $this->hasMany('App\Models\OrderProduct', 'order_id');
    }

}