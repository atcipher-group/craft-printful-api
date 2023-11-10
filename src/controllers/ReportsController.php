<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\PrintfulPlugin;
use craft\web\Controller;
use Printful\PrintfulApiClient;

class ReportsController extends Controller
{
    public function actionSettings()
    {
        $settings = PrintfulPlugin::getInstance()->getSettings();

        $results = [];
        if ($_POST) {
            $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
            $pr = new PrintfulApiClient($apiKey, 'oauth-token');
            $params = [
                'report_types' => $_POST['report_types'],
                'date_from' => $this->formatDate($_POST['date_from']['date']),
                'date_to' => $this->formatDate($_POST['date_to']['date']),
                'currency' => $_POST['currency'],
            ];
            $reports = $pr->get('reports/statistics', $params);
            unset($_POST['CRAFT_CSRF_TOKEN']);
            unset($_POST['action']);
            $results = [
                'post' => $_POST,
                'data' => $reports['store_statistics'][0][$_POST['report_types']]
            ];
        }

        return $this->renderTemplate('_printful/settings/reports.twig', [
            'settings' => $settings,
            'results' => $results
        ]);
    }

    private function formatDate(string $date)
    {
        $array = explode('/', $date);
        return $array[2] . '-' . $array[1] . '-' . $array[0];
    }
}