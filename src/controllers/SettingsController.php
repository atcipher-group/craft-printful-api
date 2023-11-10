<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\models\Settings;
use atciphergroup\craftprintfulapi\PrintfulPlugin;
use craft\web\Controller;

class SettingsController extends Controller
{
    public function actionIndex()
    {
        $settings = PrintfulPlugin::getInstance()->getSettings();

        return $this->renderTemplate('_printful/settings/index.twig', [
            'settings' => $settings
        ]);
    }

    public function actionSave()
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAdmin();

        $plugin = PrintfulPlugin::getInstance();
        $apiKey = $this->request->post('apiKey');
        $allAccessKey = $this->request->post('allAccessKey');
        $productType = $this->request->post('productType');
        $printfulShipping = $this->request->post('printfulShipping');
        $importProductImages = $this->request->post('importProductImages');

        \Craft::$app->plugins->savePluginSettings($plugin, [
            'apiKey' => $apiKey,
            'allAccessKey' => $allAccessKey,
            'productType' => $productType,
            'printfulShipping' => $printfulShipping,
            'importProductImages' => $importProductImages
        ]);
        
        $this->setSuccessFlash('You have successfully updated your settings.');
    }
}