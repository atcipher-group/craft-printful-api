<?php

namespace atciphergroup\craftprintfulapi\services;

use craft\fs\Local;
use craft\models\FsListing;
use craft\models\Volume;
use craft\services\Fs;
use craft\services\Volumes;

class Filesystems
{
    /**
     * @throws \Throwable
     */
    public function createFilesystems()
    {
        $fs = new Fs();
        $check = $fs->getFilesystemByHandle('printfulImages');

        if (is_null($check)) {
            $local = new Local();

            $local->name = 'Printful Images';
            $local->handle = 'printfulImages';
            $local->hasUrls = true;
            $local->url = '@web/uploads/printfulImages';
            $local->path = '@webroot/uploads/printfulImages';

            \Craft::$app->fs->saveFilesystem($local);
        }
    }

    public function createAssetVolumes()
    {
        $av = new Volumes();
        $check = $av->getVolumeByHandle('printfulImages');

        if (is_null($check)) {
            $model = new Volume();

            $model->name = 'Printful Images';
            $model->handle = 'printfulImages';
            $model->fsHandle = 'printfulImages';

            \Craft::$app->volumes->saveVolume($model);
        }
    }
}