<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class DexyPayService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('dexypay.base_url');
        $this->token   = config('dexypay.token');
    }

    protected function headers(array $additional = []): array
    {
        return array_merge([
            'Authorization' => "Bearer {$this->token}",
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ], $additional);
    }

    public function getBalance()
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/wallet/balance");

        return $response->throw()->json();
    }

    public function withdraw(float $amount)
    {
        $payload = ['amount' => $amount];

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/wallet/withdraw", $payload);

        return $response->throw()->json();
    }

    public function getTransaction(string $txnRef)
    {
        $url = "{$this->baseUrl}/transaction/{$txnRef}";

        $response = Http::withHeaders($this->headers())
            ->get($url);

        return $response->throw()->json();
    }

    public function verifyTransaction(string $txnRef)
    {
        $url = "{$this->baseUrl}/transaction/verify/{$txnRef}";

        $response = Http::withHeaders($this->headers())
            ->get($url);

        return $response->throw()->json();
    }

    public function getTransactions(): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/transactions");

        return $response->throw()->json();
    }

    public function initPayment(array $data)
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payment/initiate", $data);

        return $response->throw()->json();
    }

    public function charge(array $data)
    {
        // For direct charge, DexyPay may not require auth header depending on flow
        $response = Http::withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->post("{$this->baseUrl}/payment/charge", $data);

        return $response->throw()->json();
    }
}

// config/dexypay.php

return [
    'base_url' => env('DEXYPAY_BASE_URL', 'http://localhost/api/v1'),
    'token'    => env('DEXYPAY_TOKEN', ''),
];

// Example usage in a controller:

// namespace App\Http\Controllers;

// use App\Services\DexyPayService;

// class PaymentController extends Controller
// {
//     protected DexyPayService $dexy;
//
//     public function __construct(DexyPayService $dexy)
//     {
//         $this->dexy = $dexy;
//     }
//
//     public function balance()
//     {
//         return response()->json($this->dexy->getBalance());
//     }
//
//     public function withdraw()
//     {
//         $amount = request()->input('amount');
//         return response()->json($this->dexy->withdraw($amount));
//     }
//
//     public function init()
//     {
//         $data = request()->only(['name','email','amount','currency','redirect_url','pass_charge','direct_charge']);
//         return response()->json($this->dexy->initPayment($data));
//     }
//
//     public function charge()
//     {
//         $data = request()->only(['card_name','card_num','card_exp','card_secret','amount','currency_code','transaction_ref','direct_charge','payment_method']);
//         return response()->json($this->dexy->charge($data));
//     }
// }
