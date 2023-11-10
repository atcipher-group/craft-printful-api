<?php

namespace atciphergroup\craftprintfulapi\models;

use atciphergroup\craftprintfulapi\services\Shipping;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingRule;

class PrintfulShippingRule extends ShippingRule
{
    public function getHandle(): string
    {
        return 'printfulShippingRule';
    }

    public function matchOrder(Order $order): bool
    {
        if ($order->printfulVariantId) {


            $getShippingInformation = new Shipping();
            $details = $getShippingInformation->calculateShipping($order);
            $this->name = $details['name'];
            $this->description = $details['name'];
            $this->baseRate = (float)($details['rate'] * 1.2);
            return true;
        }

        return false;
    }

    public function getIsEnabled(): bool
    {
        return true;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function getPercentageRate(?int $shippingCategoryId = null): float
    {
        return 0;
    }

    public function getPerItemRate(?int $shippingCategoryId = null): float
    {
        return 0;
    }

    public function getWeightRate(?int $shippingCategoryId = null): float
    {
        return 0;
    }

    public function getBaseRate(): float
    {
        return $this->baseRate;
    }

    public function getMaxRate(): float
    {
        return 0;
    }

    public function getMinRate(): float
    {
        return 0;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
