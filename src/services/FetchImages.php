<?php

namespace atciphergroup\craftprintfulapi\services;

use craft\elements\Asset as AssetElement;
use craft\errors\ElementNotFoundException;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

class FetchImages
{
    private static function getImage($url, $fetchedImage): void
    {
        $context = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        file_put_contents($fetchedImage, fopen($url, 'r', false, stream_context_create($context)));
    }

    public static function fetchRemoteImage(string $url, $field = null, $element = null, $folderId = null, $newFilename = null): int
    {
        $uploadedAssets = 0;
        $tempPath = self::createTempPath();

        try {
            $filename = $newFilename ? AssetsHelper::prepareAssetName($newFilename) . '.' . self::getUrlExtension($url) : self::getUrlFilename($url);
            $fetchedImage = $tempPath . '/' . $filename;

            self::getImage($url, $fetchedImage);

            $result = self::createAsset($fetchedImage, $filename, $folderId, $field, $element);

            if ($result) {
                return $result;
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
            \Craft::$app->getErrorHandler()->logException($e);
        }

        return $uploadedAssets;
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    private static function createAsset($fetchedImage, $filename, $folderId, $field, $element): false|int|null
    {
        $assets = \Craft::$app->getAssets();

        if (!$folderId) {
            if (!$field) {
                throw new InvalidArgumentException('$folderId and $field cannot both be null.');
            }
            $folderId = $field->resolveDynamicPathToFolderId($element);
        }

        $folder = $assets->findFolder(['id' => $folderId]);

        $asset = new AssetElement();
        $asset->tempFilePath = $fetchedImage;
        $asset->setFilename($filename);
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(AssetElement::SCENARIO_CREATE);

        $result = \Craft::$app->getElements()->saveElement($asset, true, true, true);

        if ($result) {
            return $asset->id;
        }

        return false;
    }

    private static function createTempPath(): string
    {
        $temp = \Craft::$app->getPath()->getTempPath();
        if (!is_dir($temp)) {
            FileHelper::createDirectory($temp);
        }
        return $temp;
    }

    public static function getUrlFilename(string $url): string
    {
        $extension = self::getUrlExtension($url);
        $filename = UrlHelper::stripQueryString($url);
        $filename = pathinfo($filename, PATHINFO_FILENAME);

        return AssetsHelper::prepareAssetName("$filename.$extension");
    }

    public static function getUrlExtension(string $url): string
    {
        $extension = UrlHelper::stripQueryString($url);
        $extension = StringHelper::toLowerCase(pathinfo($extension, PATHINFO_EXTENSION));

        if (!in_array($extension, \Craft::$app->getConfig()->getGeneral()->allowedFileExtensions, true)) {
            $extension = '';
        }

        return StringHelper::toLowerCase($extension);
    }
}