<?php

namespace App\Services;

use App\Contracts\PaymentGateway;

class StripePaymentGateway implements PaymentGateway
{
    public function charge(int $amount, string $description): string
    {
        // В реальности здесь был бы вызов Stripe API
        throw new \RuntimeException('Stripe is not configured.');
    }

    public function refund(string $transactionId): void
    {
        // В реальности здесь был бы вызов Stripe API
        throw new \RuntimeException('Stripe is not configured.');
    }
}
