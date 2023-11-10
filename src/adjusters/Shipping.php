<?php

namespace atciphergroup\craftprintfulapi\adjusters;

use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;

class Shipping extends Component implements AdjusterInterface
{

    public function adjust(Order $order): array
    {
        $adjustment = new OrderAdjustment();
        $adjustment->type = 'shipping';
        $adjustment->name = 'None';
        $adjustment->amount = 0;
        $adjustment->setOrder($order);

        if (
            count($order->getLineItems()) > 0 &&
            (is_null($order->customer) === false || is_null($order->shippingAddress) === false)
        ) {
            if (
                (isset($order->customer->fullName) && $order->customer->fullName !== null) ||
                (isset($order->shippingAddress->fullName) && $order->shippingAddress->fullName !== null)
            ) {
                $shippingService = new \atciphergroup\craftprintfulapi\services\Shipping();
                $shipping = $shippingService->calculateShipping($order);

                $adjustment->name = $shipping['name'];
                $adjustment->amount = +($shipping['rate'] * 1.2);
            }
        }

        return [$adjustment];
    }
}
