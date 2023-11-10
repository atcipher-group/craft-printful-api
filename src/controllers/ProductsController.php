<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\PrintfulPlugin;
use atciphergroup\craftprintfulapi\services\Products;
use craft\web\Controller;
use craft\web\Response;
use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;
use Printful\PrintfulProducts;
use yii\web\BadRequestHttpException;

class ProductsController extends Controller
{

    protected int|bool|array $allowAnonymous = true;

    public function actionSettings()
    {
        $settings = PrintfulPlugin::getInstance()->getSettings();

        return $this->renderTemplate('_printful/settings/products.twig', [
            'settings' => $settings
        ]);
    }

    /**
     * @throws PrintfulException
     * @throws PrintfulApiException
     * @throws \Throwable
     */
    public function actionImportproducts()
    {
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');
        $productsApi = new PrintfulProducts($pr);

        // GET PRODUCT TEMPLATES
        Products::createProductTemplates();

        // CHECK AND CREATE PRODUCT AND VARIANT FIELDS
        Products::createProductFields();
        Products::createVariantFields();

        $count = 0;
        foreach ($productsApi->getProducts()->result as $product) {
            $product = $pr->get('/store/products/' . $product->id);
            $response = Products::runImport($product['sync_product'], $product['sync_variants']);
            if ($response !== 'ok') {
                $this->setFailFlash($response);
            } else {
                $this->setSuccessFlash('You have successfully imported your products and variants');
                $count++;
            }
        }

        $plugin = PrintfulPlugin::getInstance();
        \Craft::$app->plugins->savePluginSettings($plugin, [
            'productsImport' => $count,
            'productImportDateTime' => new \DateTime("now")
        ]);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionFindVariants(): Response
    {
        $this->requirePostRequest();

        $colour = $this->request->getBodyParam('colour');
        $size = $this->request->getBodyParam('size');
        $product = $this->request->getBodyParam('product');

        $variant = \craft\commerce\elements\Variant::find();
        $variant->productId = $product;
        $variant->printfulAttrColor = $colour;
        $variant->printfulAttrSize = $size;
        $result = $variant->one();

        return $this->asJson(['id' => $result->id]);
    }
}
