<?php

namespace atciphergroup\craftprintfulapi\services;

use atciphergroup\craftprintfulapi\Plugin as PrintfulPlugin;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\StringHelper;
use craft\models\FieldGroup;
use craft\models\FieldLayoutTab;
use craft\services\Fields;
use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;
use yii\base\Exception;

class Orders
{
    private array $statuses = [
        ['color' => 'blue', 'name' => 'Awaiting Confirmation', 'handle' => 'draft', 'default' => false],
        ['color' => 'yellow', 'name' => 'Processing', 'handle' => 'processing', 'default' => false],
        ['color' => 'orange', 'name' => 'Dispatched', 'handle' => 'dispatched', 'default' => false],
        ['color' => 'purple', 'name' => 'Delivered', 'handle' => 'delivered', 'default' => false],
        ['color' => 'pink', 'name' => 'Returned', 'handle' => 'returned', 'default' => false],
        ['color' => 'red', 'name' => 'Cancelled', 'handle' => 'cancelled', 'default' => false],
        ['color' => 'red', 'name' => 'Failed', 'handle' => 'failed', 'default' => false],
        ['color' => 'green', 'name' => 'Complete', 'handle' => 'complete', 'default' => false],
        ['color' => 'light', 'name' => 'Out Of Stock', 'handle' => 'outOfStock', 'default' => false],
    ];

    public function buildCustomer($customer, $address): array
    {
        return [
            'name' => $customer->fullname ?? $address->fullName,
            'company' => $customer->company ?? '',
            'address1' => $address->addressLine1 ?? '',
            'address2' => $address->addressLine2 ?? '',
            'city' => $address->locality ?? '',
            'state_code' => $address->administrativeArea ?? '',
            'country_code' => $address->countryCode ?? 'GB',
            'zip' => $address->postalCode ?? '',
            'email' => $customer->email,
        ];
    }

    public function buildOrderItems($items): array
    {
        $array = [];
        foreach ($items as $item) {
            $array[] = [
                'external_variant_id' => $item->getPurchasable()->printfulVariantId,
                'quantity' => $item->qty,
            ];
        }

        return $array;
    }

    /**
     * @throws PrintfulException
     * @throws \Throwable
     * @throws ElementNotFoundException
     * @throws PrintfulApiException
     * @throws Exception
     */
    public function submitOrder(Order $order): void
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $customer = $this->buildCustomer($order->customer, $order->shippingAddress);
        $items = $this->buildOrderItems($order->getLineItems());

        $data = [
            'recipient' => $customer,
            'items' => $items
        ];

        $params = [];
//        if (!$order->printfulCoolingOff) {
//            $params[] = [
//                'confirm' => true
//            ];
//        }

        $orderDetails = $pr->post('orders', $data, $params);

        $order->printfulOrderNumber = $orderDetails['id'];
        \Craft::$app->getElements()->saveElement($order);
    }

    /**
     * @throws PrintfulException
     * @throws PrintfulApiException
     */
    public function finaliseOrder(Order $order): void
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $pr->post('/orders/' . $order->printfulOrderNumber . '/confirm');
    }

    /**
     * @throws PrintfulException
     * @throws PrintfulApiException
     */
    public function cancelOrder(Order $order): void
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $pr->delete('/orders/' . $order->printfulOrderNumber);
    }

    /**
     * @throws PrintfulException
     * @throws ElementNotFoundException
     * @throws \Throwable
     * @throws Exception
     * @throws PrintfulApiException
     */
    public function calculateProfitLoss(Order $order): void
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $customer = $this->buildCustomer($order->customer, $order->shippingAddress);
        $items = $this->buildOrderItems($order->getLineItems());

        $data = [
            'recipient' => $customer,
            'items' => $items
        ];

        $result = $pr->post('/orders/estimate-costs', $data);
        $profitLoss = $order->totalPaid - $result['costs']['total'];

        $order->orderProfitLoss = $profitLoss * 100;
        \Craft::$app->getElements()->saveElement($order);
    }

    /**
     * @throws \Throwable
     */
    public function buildCoolingOffOption(): void
    {
        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Orders') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Orders";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Orders') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $fields = new Fields();
        $field = \Craft::$app->fields->getFieldByHandle('printfulCoolingOff');
        if (is_null($field)) {
            $field = $fields->createField([
                'name' => 'Printful UK/EU Cooling Off Period',
                'handle' => 'printfulCoolingOff',
                'type' => 'craft\fields\Lightswitch',
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($field);
            $field = \Craft::$app->fields->getFieldByHandle('printfulCoolingOff');
        }

        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        $newElement = [
            'type' => CustomField::class,
            'layout' => $fieldLayout,
            'fieldUid' => $field->uid,
            'required' => false
        ];

        if (empty($tabs)) {
            $tab = new FieldLayoutTab();
            $tab->name = 'Printful';
            $tab->setLayout($fieldLayout);
            $tab->setElements([$newElement]);

            $fieldLayout->setTabs([$tab]);
        }

        \Craft::$app->fields->saveLayout($fieldLayout);
    }

    /**
     * @throws \Throwable
     */
    public function buildOrderProfitField(): void
    {
        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Orders') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Orders";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Orders') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $fields = new Fields();
        $field = \Craft::$app->fields->getFieldByHandle('orderProfitLoss');
        if (is_null($field)) {
            $field = $fields->createField([
                'name' => 'Order Profit/Loss',
                'handle' => 'orderProfitLoss',
                'type' => 'craft\fields\Money',
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($field);
            $field = \Craft::$app->fields->getFieldByHandle('orderProfitLoss');
        }

        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        $newElement = [
            'type' => CustomField::class,
            'layout' => $fieldLayout,
            'fieldUid' => $field->uid,
            'required' => false
        ];

        if (empty($tabs)) {
            $tab = new FieldLayoutTab();
            $tab->name = 'Printful';
            $tab->setLayout($fieldLayout);
            $tab->setElements([$newElement]);

            $fieldLayout->setTabs([$tab]);
        }

        \Craft::$app->fields->saveLayout($fieldLayout);
    }

    /**
     * @throws \Throwable
     */
    public function buildOrderNumberField(): void
    {
        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Orders') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Orders";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Orders') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $fields = new Fields();
        $field = \Craft::$app->fields->getFieldByHandle('printfulOrderNumber');
        if (is_null($field)) {
            $field = $fields->createField([
                'name' => 'Printful Order Number',
                'handle' => 'printfulOrderNumber',
                'type' => 'craft\fields\PlainText',
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($field);
            $field = \Craft::$app->fields->getFieldByHandle('printfulOrderNumber');
        }

        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        $newElement = [
            'type' => CustomField::class,
            'layout' => $fieldLayout,
            'fieldUid' => $field->uid,
            'required' => false
        ];

        if (empty($tabs)) {
            $tab = new FieldLayoutTab();
            $tab->name = 'Printful';
            $tab->setLayout($fieldLayout);
            $tab->setElements([$newElement]);

            $fieldLayout->setTabs([$tab]);
        }

        \Craft::$app->fields->saveLayout($fieldLayout);
    }

    /**
     * @throws \Throwable
     */
    public function removeGeneratedOrderFields(): void
    {
        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $fieldLayout->setTabs([]);

        $field = \Craft::$app->fields->getFieldByHandle('printfulCoolingOff');
        if (!is_null($field)) {
            \Craft::$app->fields->deleteField($field);
        }

        $field = \Craft::$app->fields->getFieldByHandle('orderProfitLoss');
        if (!is_null($field)) {
            \Craft::$app->fields->deleteField($field);
        }

        $field = \Craft::$app->fields->getFieldByHandle('printfulOrderNumber');
        if (!is_null($field)) {
            \Craft::$app->fields->deleteField($field);
        }

        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Orders') {
                $group = \Craft::$app->fields->getGroupById($row->id);
                \Craft::$app->fields->deleteGroup($group);
            }
        }
    }

    /**
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     */
    public function createOrderStatuses(): void
    {
        foreach ($this->statuses as $row) {
            $os = new OrderStatus();
            $os->name = $row['name'];
            $os->handle = $row['handle'];
            $os->color = $row['color'];
            $os->default = $row['default'];

            Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($os);
        }
    }

    /**
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     */
    public function removeOrderStatuses(): void
    {
        foreach ($this->statuses as $row) {
            $os = Plugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle($row['handle']);
            Plugin::getInstance()->getOrderStatuses()->deleteOrderStatusById($os->id);
        }
    }

    /**
     * @throws Exception
     * @throws \Throwable
     */
    public function buildShippingFields(string $fieldName, string $type = 'text'): void
    {
        $typeField = 'craft\fields\PlainText';
        if ($type !== 'text') {
            $typeField = 'craft\fields\Date';
        }

        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Shipping') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Shipping";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Shipping') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $camelcase = StringHelper::camelCase($fieldName);

        $fields = new Fields();
        $field = \Craft::$app->fields->getFieldByHandle($camelcase);
        if (is_null($field)) {
            $field = $fields->createField([
                'name' => $fieldName,
                'handle' => $camelcase,
                'type' => $typeField,
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($field);
            $field = \Craft::$app->fields->getFieldByHandle($camelcase);
        }

        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        $newElement = [
            'type' => CustomField::class,
            'layout' => $fieldLayout,
            'fieldUid' => $field->uid,
            'required' => false
        ];

        if (empty($tabs)) {
            $tab = new FieldLayoutTab();
            $tab->name = 'Printful Shipping';
            $tab->setLayout($fieldLayout);
            $tab->setElements([$newElement]);

            $fieldLayout->setTabs([$tab]);
        }

        \Craft::$app->fields->saveLayout($fieldLayout);
    }

    public function removeGeneratedShippingFields(string $fieldName): void
    {
        $camelcase = StringHelper::camelCase($fieldName);

        $field = \Craft::$app->fields->getFieldByHandle($camelcase);
        if (!is_null($field)) {
            \Craft::$app->fields->deleteField($field);
        }
    }

    public function removeGeneratedShippingFieldGroup(): void
    {
        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $fieldLayout->setTabs([]);

        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Shipping') {
                $group = \Craft::$app->fields->getGroupById($row->id);
                \Craft::$app->fields->deleteGroup($group);
            }
        }
    }
}
