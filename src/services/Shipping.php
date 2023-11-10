<?php

namespace atciphergroup\craftprintfulapi\services;

use atciphergroup\craftprintfulapi\PrintfulPlugin;
use craft\commerce\elements\Order;
use Printful\PrintfulApiClient;

class Shipping
{
    public function calculateShipping(Order $order)
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $orderService = new Orders();
        $customer = $orderService->buildCustomer($order->customer, $order->shippingAddress);
        $items = $orderService->buildOrderItems($order->getLineItems());

        $data = [
            'recipient' => $customer,
            'items' => $items
        ];

        $rates = $pr->post('shipping/rates', $data);
        return $rates[0];
    }
}