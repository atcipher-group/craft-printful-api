<?php

namespace atciphergroup\craftprintfulapi;

use atciphergroup\craftprintfulapi\services\Orders;
use Craft;
use atciphergroup\craftprintfulapi\models\Settings;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
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
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
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
                Craft::$app->getProjectConfig()->rebuild();
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
                Craft::$app->getProjectConfig()->rebuild();
            }
        );

        // INITIALISE THE CONTROL PANEL
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'printful-api/settings',
                    'label' => 'Printful',
                    'icon' => '@atciphergroup/craftprintfulapi/icon-mask.svg'
                ];
            }
        );

        // SET THE CLEAN ROUTES TO CONTROLLER FUNCTIONS
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // SETTINGS PAGES
                $event->rules['printful-api/settings'] = 'craft-printful-api/settings/index';
                $event->rules['printful-api/settings/categories'] = 'craft-printful-api/category/settings';
                $event->rules['printful-api/settings/products'] = 'craft-printful-api/products/settings';
                $event->rules['printful-api/settings/orders'] = 'craft-printful-api/orders/settings';
                $event->rules['printful-api/settings/store-information'] = 'craft-printful-api/printful/storeinformation';
                $event->rules['printful-api/settings/tax'] = 'craft-printful-api/printful/taxrates';
                $event->rules['printful-api/settings/reports'] = 'craft-printful-api/reports/settings';
                $event->rules['printful-api/settings/webhooks'] = 'craft-printful-api/webhooks/settings';
                // IMPORT ACTIONS
                $event->rules['printful-api/products/import'] = 'craft-printful-api/products/importproducts';
                $event->rules['printful-api/products/sync'] = 'craft-printful-api/products/getsyncproducts';
                $event->rules['printful-api/category/import'] = 'craft-printful-api/category/import';
                // SAVE ACTIONS
                $event->rules['printful-api/settings/save'] = 'craft-printful-api/settings/save';
                // ADDITIONAL PAGES
                $event->rules['printful-api/additional/colours'] = 'craft-printful-api/additional/colours';
                $event->rules['printful-api/additional/colours/copy'] = 'craft-printful-api/additional/copycolours';
                // JSON PAGES
                $event->rules['printful-api/front-end/variants'] = 'craft-printful-api/products/findvariants';
            }
        );

        // SET THE CLEAN ROUTES TO CONTROLLER FUNCTIONS
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // WEBHOOK ACTIONS
                $event->rules['printful-api/webhooks/check'] = 'craft-printful-api/webhooks/check';
                $event->rules['printful-api/webhooks/create'] = 'craft-printful-api/webhooks/create';
                $event->rules['printful-api/webhooks/remove'] = 'craft-printful-api/webhooks/remove';
                $event->rules['printful-api/webhooks/update'] = 'craft-printful-api/webhooks/update';
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
                $service->cancelOrder($order);
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
        return Craft::$app->view->renderTemplate('craft-printful-api/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    public function getSettingsResponse(): mixed
    {
        $url = \craft\helpers\UrlHelper::cpUrl('printful-api/settings');
        return \Craft::$app->controller->redirect($url);
    }

    public function attachEventHandlers()
    {

    }
}
