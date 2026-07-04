<?php

namespace App\Contracts;

interface PaymentGateway
{
    /**
     * @return string Transaction ID
     * @throws \RuntimeException If payment fails
     */
    public function charge(int $amount, string $description): string;

    /**
     * @throws \RuntimeException If refund fails
     */
    public function refund(string $transactionId): void;
}
