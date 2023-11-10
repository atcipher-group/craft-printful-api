<?php

namespace atciphergroup\craftprintfulapi\services;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\services\OrderStatuses;

class Webhooks
{
    public function packageShipped($data): void
    {
        $order = $data->order;
        $shipping = $data->shipment;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle('dispatched');

        $data->orderStatusId = $status->id;
        $details->printfulShippingCarrier = $shipping->carrier;
        $details->printfulShippingTrackingNumber = $shipping->tracking_number;
        $details->printfulShippingTrackingUrl = $shipping->tracking_url;
        $details->printfulShippingService = $shipping->service;
        $details->printfulShippingDate = new \DateTime($shipping->ship_date);

        \Craft::$app->elements->saveElement($details);
    }

    public function packageReturned($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle('returned');

        $details->orderStatusId = $status->id;
        $details->printfulShippingReturnReason = $data->reason;

        \Craft::$app->elements->saveElement($details);
    }

    public function orderCreated($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle('draft');

        $details->orderStatusId = $status->id;

        \Craft::$app->elements->saveElement($details);
    }

    public function orderUpdated($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        switch ($order->status) {
            case 'draft' :
            case 'onhold' :
                $status = 'draft';
                break;
            case 'pending' :
                $status = 'processing';
                break;
            case 'failed' :
                $status = 'failed';
                break;
            case 'canceled' :
                $status = 'cancelled';
                break;
            case 'fulfilled' :
                $status = 'delivered';
                break;
            default:
                $status = 'processing';
        }

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle($status);

        $details->orderStatusId = $status->id;

        \Craft::$app->elements->saveElement($details);
    }

    public function orderFailed($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle('failed');

        $details->orderStatusId = $status->id;
        $details->printfulShippingReturnReason = $data->reason;

        \Craft::$app->elements->saveElement($details);
    }

    public function orderCancelled($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle('cancelled');

        $details->orderStatusId = $status->id;
        $details->printfulShippingReturnReason = $data->reason;

        \Craft::$app->elements->saveElement($details);
    }

    public function orderOnHold($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle('draft');

        $details->orderStatusId = $status->id;
        $details->printfulShippingReturnReason = $data->reason;

        \Craft::$app->elements->saveElement($details);
    }

    public function orderRemoveHold($data): void
    {
        $order = $data->order;

        $orderElements = Order::find();
        $orderElements->printfulOrderNumber = $order->external_id;
        $details = $orderElements->one();

        switch ($order->status) {
            case 'draft' :
            case 'onhold' :
                $status = 'draft';
                break;
            case 'pending' :
                $status = 'processing';
                break;
            case 'failed' :
                $status = 'failed';
                break;
            case 'canceled' :
                $status = 'cancelled';
                break;
            case 'fulfilled' :
                $status = 'delivered';
                break;
            default:
                $status = 'processing';
        }

        $orderStatus = new OrderStatuses();
        $status = $orderStatus->getOrderStatusByHandle($status);

        $details->orderStatusId = $status->id;
        $details->printfulShippingReturnReason = $data->reason;

        \Craft::$app->elements->saveElement($details);
    }

    public function productDeleted($data): void
    {
        $product = $data->sync_product;

        $result = Product::find()->title($product->name)->one();
        $result->enabled = false;

        \Craft::$app->elements->saveElement($result);
    }

    public function stockUpdated($data): void
    {
        $variantIds = $data->variant_stock->out;

        foreach ($variantIds as $key => $value) {
            $variant = Variant::find();
            $variant->printfulVariantId = $value;
            $result = $variant->one();

            $result->hasUnlimitedStock = false;
            $result->stock = 0;

            \Craft::$app->elements->saveElement($result);
        }
    }
}