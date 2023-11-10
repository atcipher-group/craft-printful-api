<?php

namespace atciphergroup\craftprintfulapi;

use atciphergroup\craftprintfulapi\models\ShippingMethod;
use atciphergroup\craftprintfulapi\services\Orders;
use atciphergroup\craftprintfulapi\services\Products;
use Craft;
use atciphergroup\craftprintfulapi\models\Settings;
use atciphergroup\craftprintfulapi\services\Printful;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\adjusters\Shipping;
use craft\commerce\elements\Order;
use craft\commerce\services\OrderAdjustments;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Plugins;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Printful plugin
 *
 * @method static PrintfulPlugin getInstance()
 * @method Settings getSettings()
 */
class PrintfulPlugin extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });

        // SETUP A FIELD TO CHECK FOR A COOLING OFF PERIOD
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                $service = new Orders();
                // $service->buildCoolingOffOption();
                $service->buildOrderProfitField();
                $service->buildOrderNumberField();
                $service->buildShippingFields('Printful Shipping Carrier');
                $service->buildShippingFields('Printful Shipping Service');
                $service->buildShippingFields('Printful Shipping Tracking Number');
                $service->buildShippingFields('Printful Shipping Tracking URL');
                $service->buildShippingFields('Printful Shipping Date', 'date');
                $service->buildShippingFields('Printful Shipping Return Reason');
                $service->createOrderStatuses();
            }
        );

        // REMOVE COOLING OFF PERIOD
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_UNINSTALL_PLUGIN,
            function (PluginEvent $event) {
                $service = new Orders();
                $service->removeGeneratedOrderFields();
                $service->removeOrderStatuses();
                $service->removeGeneratedShippingFields('Printful Shipping Carrier');
                $service->removeGeneratedShippingFields('Printful Shipping Service');
                $service->removeGeneratedShippingFields('Printful Shipping Tracking Number');
                $service->removeGeneratedShippingFields('Printful Shipping Tracking URL');
                $service->removeGeneratedShippingFields('Printful Shipping Date');
                $service->removeGeneratedShippingFields('Printful Shipping Return Reason');
                $service->removeGeneratedShippingFieldGroup();
            }
        );

        // INITIALISE THE CONTROL PANEL
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => '_printful/settings',
                    'label' => 'Printful',
                    'icon' => '@arthurquinn/craftprintful/icons/t-shirt.svg'
                ];
            }
        );

        // SET THE CLEAN ROUTES TO CONTROLLER FUNCTIONS
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // SETTINGS PAGES
                $event->rules['_printful/settings'] = '_printful/settings/index';
                $event->rules['_printful/settings/categories'] = '_printful/category/settings';
                $event->rules['_printful/settings/products'] = '_printful/products/settings';
                $event->rules['_printful/settings/orders'] = '_printful/orders/settings';
                $event->rules['_printful/settings/store-information'] = '_printful/printful/storeinformation';
                $event->rules['_printful/settings/tax'] = '_printful/printful/taxrates';
                $event->rules['_printful/settings/reports'] = '_printful/reports/settings';
                $event->rules['_printful/settings/webhooks'] = '_printful/webhooks/settings';
                // IMPORT ACTIONS
                $event->rules['_printful/products/import'] = '_printful/products/importproducts';
                $event->rules['_printful/products/sync'] = '_printful/products/getsyncproducts';
                $event->rules['_printful/category/import'] = '_printful/category/import';
                // SAVE ACTIONS
                $event->rules['_printful/settings/save'] = '_printful/settings/save';
                // ADDITIONAL PAGES
                $event->rules['_printful/additional/colours'] = '_printful/additional/colours';
                $event->rules['_printful/additional/colours/copy'] = '_printful/additional/copycolours';
                // JSON PAGES
                $event->rules['_printful/front-end/variants'] = '_printful/products/findvariants';
            }
        );

        // SET THE CLEAN ROUTES TO CONTROLLER FUNCTIONS
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // WEBHOOK ACTIONS
                $event->rules['_printful/webhooks/check'] = '_printful/webhooks/check';
                $event->rules['_printful/webhooks/create'] = '_printful/webhooks/create';
                $event->rules['_printful/webhooks/remove'] = '_printful/webhooks/remove';
                $event->rules['_printful/webhooks/update'] = '_printful/webhooks/update';
            }
        );

        // SET THE ORDER EVENT TO SUBMIT ORDER DETAILS TO PRINTFUL
        Event::on(
            Order::class,
            Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $event) {
                // @var Order $order
                $order = $event->sender;
                $service = new Orders();
                $service->submitOrder($order);
                $service->calculateProfitLoss($order);
            }
        );

        // SET THE ORDER EVENT TO SUBMIT ORDER DETAILS TO PRINTFUL
        Event::on(
            Order::class,
            Order::EVENT_AFTER_DELETE,
            function (Event $event) {
                // @var Order $order
                $order = $event->sender;
                $service = new Orders();
//                $service->cancelOrder($order);
            }
        );

        // SET SHIPPING ADJUSTERS
        Event::on(
            OrderAdjustments::class,
            OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS,
            function (RegisterComponentTypesEvent $event) {
                $adjusters = $event->types;
                foreach ($adjusters as $key => $adj) {
                    if ($adj === Shipping::class && $this->getSettings()->printfulShipping) {
                        $adjusters[$key] = \atciphergroup\craftprintfulapi\adjusters\Shipping::class;
                    }
                }

                $event->types = $adjusters;
            }
        );
    }

    /**
     * @throws InvalidConfigException
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('_printful/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    public function getSettingsResponse(): mixed
    {
        $url = \craft\helpers\UrlHelper::cpUrl('_printful/settings');
        return \Craft::$app->controller->redirect($url);
    }

    public function attachEventHandlers()
    {

    }
}
