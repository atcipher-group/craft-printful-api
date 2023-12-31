<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\Plugin as PrintfulPlugin;
use craft\web\Controller;
use Printful\PrintfulApiClient;

class OrdersController extends Controller
{
    public function actionSettings()
    {
        $settings = PrintfulPlugin::getInstance()->getSettings();

        return $this->renderTemplate('craft-printful-api/settings/orders.twig', [
            'settings' => $settings
        ]);
    }

    public function actionList()
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');
        $orders = $pr->get('orders', ['limit' => 10]);
    }
}
