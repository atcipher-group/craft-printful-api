<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\Plugin as PrintfulPlugin;
use craft\web\Controller;
use Printful\PrintfulApiClient;

class AdditionalController extends Controller
{
    public function actionColours()
    {
        $allAccessKey = PrintfulPlugin::getInstance()->getSettings()->allAccessKey;
        $allPr = new PrintfulApiClient($allAccessKey, 'oauth-token');

        // GET PRODUCT TEMPLATES
        $productTemplates = $allPr->get('product-templates');
        $colours = [];
        foreach ($productTemplates['items'] as $item) {
            if ($item['colors']) {
                foreach ($item['colors'] as $colour) {
                    $colours[] = [
                        'name' => $colour['color_name'],
                        'code' => $colour['color_codes']
                    ];
                }
            }
        }

        return $this->renderTemplate('craft-printful-api/pages/colours.twig', [
            'colours' => $colours
        ]);
    }

    public function actionCopycolours()
    {
        $allAccessKey = PrintfulPlugin::getInstance()->getSettings()->allAccessKey;
        $allPr = new PrintfulApiClient($allAccessKey, 'oauth-token');

        // GET PRODUCT TEMPLATES
        $productTemplates = $allPr->get('product-templates');
        $colours = [];
        foreach ($productTemplates['items'] as $item) {
            if ($item['colors']) {
                foreach ($item['colors'] as $colour) {
                    $colours[] = [
                        'name' => $colour['color_name'],
                        'code' => $colour['color_codes']
                    ];
                }
            }
        }

        return $this->renderTemplate('craft-printful-api/pages/copy_colours.twig', [
            'colours' => $colours
        ]);
    }
}