<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\Plugin as PrintfulPlugin;
use atciphergroup\craftprintfulapi\services\Webhooks;
use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;
use Printful\PrintfulWebhook;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;

class WebhooksController extends Controller
{
    protected int|bool|array $allowAnonymous = ['update'];

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->enableCsrfValidation = false;
    }

    public function actionSettings()
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $webhooks = new PrintfulWebhook($pr);
        $data = $webhooks->getRegisteredWebhooks();

        return $this->renderTemplate('craft-printful-api/settings/webhooks.twig', [
            'choices' => $data
        ]);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionUpdate(): \yii\web\Response
    {
        $this->requirePostRequest();

        $payload = json_decode(Craft::$app->getRequest()->getRawBody());

        $service = new Webhooks();
        switch ($payload->type) {
            case 'package_shipped' :
                $service->packageShipped($payload->data);
                break;
            case 'package_returned' :
                $service->packageReturned($payload->data);
                break;
            case 'order_created' :
                $service->orderCreated($payload->data);
                break;
            case 'order_updated' :
                $service->orderUpdated($payload->data);
                break;
            case 'order_failed' :
                $service->orderFailed($payload->data);
                break;
            case 'order_canceled' :
                $service->orderCancelled($payload->data);
                break;
            case 'order_put_hold' :
            case 'order_put_hold_approval' :
                $service->orderOnHold($payload->data);
                break;
            case 'order_remove_hold' :
                $service->orderRemoveHold($payload->data);
                break;
            case 'product_deleted' :
                $service->productDeleted($payload->data);
                break;
            case 'stock_updated' :
                $service->stockUpdated($payload->data);
                break;
        }

        return $this->asJson($payload);
    }

    /**
     * @throws PrintfulException
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function actionCreate()
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAdmin();

        $choices = $this->request->post('webhookOptions');

        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $webhooks = new PrintfulWebhook($pr);
        $webhooks->registerWebhooks(
            Craft::$app->getSites()->getCurrentSite()->getBaseUrl() . '/printful-api/webhooks/update',
            $choices
        );

        $this->setSuccessFlash('You have successfully updated your webhook choices.');
    }

    public function actionRemove()
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAdmin();

        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $webhooks = new PrintfulWebhook($pr);
        $webhooks->disableWebhooks();

        $this->setSuccessFlash('You have successfully removed your webhooks.');
    }
}