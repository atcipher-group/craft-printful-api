<?php

namespace atciphergroup\craftprintfulapi\models;

use atciphergroup\craftprintfulapi\services\Shipping;
use craft\commerce\base\ShippingMethod;
use craft\commerce\elements\Order;

class PrintfulShippingMethod extends ShippingMethod
{
    private float $baseRate = 0;

    public function getType(): string
    {
        return 'printful-api';
    }

    public function getId(): ?int
    {
        return null;
    }

    public function getName(): string
    {
        return 'Printful Shipping Method';
    }

    public function getHandle(): string
    {
        return 'printfulShippingMethod';
    }

    public function getCpEditUrl(): string
    {
        return '';
    }

    public function getShippingRules(): array
    {
        $sr = new PrintfulShippingRule();
        $sr->name = $this->name;
        $sr->description = 'This is a Test';
        $sr->baseRate = $this->baseRate;

        return [$sr];
    }

    public function getIsEnabled(): bool
    {
        return true;
    }

//    public function matchOrder(Order $order): bool
//    {
//        if ($order->printfulVariantId) {
//            $getShippingInformation = new Shipping();
//            $details = $getShippingInformation->calculateShipping($order);
//            $this->name = $details['name'];
//            $this->baseRate = $details['rate'] * 1.2;
//            return true;
//        }
//
//        return false;
//    }
}
