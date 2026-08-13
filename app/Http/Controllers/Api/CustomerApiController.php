<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;

class CustomerApiController extends Controller
{
    public function index()
    {
        $customers = Customer::all()->map(fn ($c) => [
            'id' => $c->customer_no,
            'customerNo' => $c->customer_no,
            'name' => $c->name,
            'phone' => $c->phone ?? '-',
            'email' => $c->email ?? '-',
            'type' => $c->category ?? 'Regular',
            'address' => $c->address ?? '',
        ]);

        return response()->json(['customers' => $customers]);
    }
}
