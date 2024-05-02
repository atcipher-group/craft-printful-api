<?php

namespace atciphergroup\craftprintfulapi\services;

use atciphergroup\craftprintfulapi\helpers\ArrayHelper;
use atciphergroup\craftprintfulapi\Plugin as PrintfulPlugin;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\services\ProductTypes;
use craft\elements\Asset;
use craft\elements\Category;
use craft\errors\ElementNotFoundException;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Dropdown;
use craft\fields\PlainText;
use craft\fields\Url;
use craft\helpers\StringHelper;
use craft\models\FieldGroup;
use craft\services\Fields;
use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class Products
{
    private static array $variantFields = [
        ['field' => 'id', 'type' => 'craft\fields\PlainText'],
        ['field' => 'image', 'type' => 'craft\fields\Assets'],
        ['field' => 'cdn', 'type' => 'craft\fields\Url']
    ];

    /**
     * @throws \Throwable
     */
    private static function productAttributes(array $array = []): void
    {
        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Variants') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Variants";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Variants') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $field = \Craft::$app->fields->getFieldByHandle('printfulAttr' . ucfirst($array['label']));
        if (is_null($field)) {
            $dropdown = new Dropdown();
            $dropdown->name = 'Printful ' . ucfirst($array['label']);
            $dropdown->handle = 'printfulAttr' . ucfirst($array['label']);
            $dropdown->options = $array['options'];
            $dropdown->groupId = $fieldGroupCheck;
            $dropdown->searchable = false;
            \Craft::$app->fields->saveField($dropdown);
        } else {
            if (ArrayHelper::countArrayDiff($field->options, $array['options']) > 0) {
                $field->options = array_unique(array_merge($field->options, $array['options']), SORT_REGULAR);
                \Craft::$app->fields->saveField($field);
            }
        }
    }

    public static function createProductTemplates(): void
    {
        $allAccessKey = PrintfulPlugin::getInstance()->getSettings()->allAccessKey;
        $allPr = new PrintfulApiClient($allAccessKey, 'oauth-token');

        // GET PRODUCT TEMPLATES
        $productTemplates = $allPr->get('product-templates');
        foreach ($productTemplates['items'] as $item) {
            if ($item['colors']) {
                $colours = [];
                foreach ($item['colors'] as $colour) {
                    $colours[] = [
                        'label' => $colour['color_name'],
                        'value' => $colour['color_name'],
                        'default' => ''
                    ];
                }
                self::productAttributes([
                    'label' => 'color',
                    'options' => $colours
                ]);
            }
            if ($item['sizes']) {
                $sizes = [];
                foreach ($item['sizes'] as $k => $size) {
                    $sizes[] = [
                        'label' => $size,
                        'value' => $size,
                        'default' => ''
                    ];
                }
                self::productAttributes([
                    'label' => 'size',
                    'options' => $sizes
                ]);
            }
        }
    }

    /**
     * @throws \Throwable
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function createProductFields()
    {
        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Product') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Product";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Product') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $fields = new Fields();
        $description = \Craft::$app->fields->getFieldByHandle('printfulDescription');
        if (is_null($description)) {
            $description = $fields->createField([
                'name' => 'Printful Description',
                'handle' => 'printfulDescription',
                'type' => 'craft\fields\PlainText',
                'multiline' => true,
                'initialRows' => 6,
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($description);
            $description = \Craft::$app->fields->getFieldByHandle('printfulDescription');
        }

        $cdnSettings = PrintfulPlugin::getInstance()->getSettings()->importProductImages;

        $image = \Craft::$app->fields->getFieldByHandle('printfulImage');
        if (is_null($image)) {
            $image = $fields->createField([
                'name' => 'Printful Image',
                'handle' => 'printfulImage',
                'type' => 'craft\fields\Assets',
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($image);
            $image = \Craft::$app->fields->getFieldByHandle('printfulImage');
        }

        $cdn = \Craft::$app->fields->getFieldByHandle('printfulCdnImage');
        if (is_null($cdn)) {
            $cdn = $fields->createField([
                'name' => 'Printful CDN Image',
                'handle' => 'printfulCdnImage',
                'type' => 'craft\fields\Url',
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($cdn);
            $cdn = \Craft::$app->fields->getFieldByHandle('printfulCdnImage');
        }

        $categories = \Craft::$app->fields->getFieldByHandle('printfulCategories');
        $group = \Craft::$app->categories->getGroupByHandle('productCategories');
        if (is_null($categories)) {
            $categories = $fields->createField([
                'name' => 'Printful Categories',
                'handle' => 'printfulCategories',
                'type' => 'craft\fields\Categories',
                'source' => 'group:' . $group->uid,
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($categories);
            $categories = \Craft::$app->fields->getFieldByHandle('printfulDescription');
        }

        // ADD THE FIELD TO THE PRODUCT CONTENT
        $productType = new ProductTypes();
        $pt = $productType->getProductTypeByHandle(PrintfulPlugin::getInstance()->getSettings()->productType);
        $fieldLayout = $pt->getProductFieldLayout();
        $tabs = $fieldLayout->getTabs();

        $descCheck = false;
        $catCheck = false;
        $imgCheck = false;
        $cdnCheck = false;
        foreach ($tabs[0]->getLayout()->getCustomFields() as $i => $element) {
            if ($element instanceof PlainText && $element->handle === 'printfulDescription') {
                $descCheck = true;
            }
            if ($element instanceof Categories && $element->handle === 'printfulCategories') {
                $catCheck = true;
            }
            if ($element instanceof Assets && $element->handle === 'printfulImage') {
                $imgCheck = true;
            }
            if ($element instanceof Url && $element->handle === 'printfulCdnImage') {
                $cdnCheck = true;
            }
        }

        if (!$catCheck) {
            $newElements = [
                'type' => CustomField::class,
                'fieldUid' => $categories->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElements]));
            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }
        if (!$descCheck) {
            $newElements = [
                'type' => CustomField::class,
                'fieldUid' => $description->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElements]));
            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }
        if (!$imgCheck) {
            $newElements = [
                'type' => CustomField::class,
                'fieldUid' => $image->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElements]));
            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }
        if (!$cdnCheck) {
            $newElements = [
                'type' => CustomField::class,
                'fieldUid' => $cdn->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElements]));
            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }
    }

    /**
     * @throws \Throwable
     * @throws InvalidConfigException
     * @throws Exception
     */
    public static function createVariantFields()
    {
        $cdnSettings = PrintfulPlugin::getInstance()->getSettings()->importProductImages;

        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Variants') {
                $fieldGroupCheck = $row->id;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Variants";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Product') {
                    $fieldGroupCheck = $row->id;
                }
            }
        }

        $fields = new Fields();
        foreach (self::$variantFields as $item) {
            $label = ucfirst(str_replace('_', '', $item['field']));
            $value = ucfirst(str_replace('_', ' ', $item['field']));
            $field = \Craft::$app->fields->getFieldByHandle('printfulVariant' . ucfirst($label));
            if (is_null($field)) {
                $field = $fields->createField([
                    'name' => 'Variant ' . $value,
                    'handle' => 'printfulVariant' . $label,
                    'type' => $item['type'],
                    'groupId' => $fieldGroupCheck,
                    'searchable' => false,
                ]);
                \Craft::$app->fields->saveField($field);
            }

            // ADD THE FIELD TO THE PRODUCT CONTENT
            $productType = new ProductTypes();
            $pt = $productType->getProductTypeByHandle(PrintfulPlugin::getInstance()->getSettings()->productType);
            $fieldLayout = $pt->getVariantFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $check = false;
            foreach ($tabs[0]->getLayout()->getCustomFields() as $i => $element) {
                if ($element instanceof $item['type'] && $element->handle === 'printfulVariant' . $label) {
                    $check = true;
                }
            }

            if (!$check) {
                $newElement = [
                    'type' => $item['type'],
                    'fieldUid' => $field->uid,
                    'required' => false
                ];

                try {
                    $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElement]));
                    $fieldLayout->setTabs($tabs);
                    \Craft::$app->fields->saveLayout($fieldLayout);
                } catch (\Exception $e) {
                    echo $e->getMessage(); die();
                }
            }

            unset($check);
            unset($field);
        }

        self::addProductAttributes();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    private static function addProductAttributes()
    {
        $productType = new ProductTypes();
        $pt = $productType->getProductTypeByHandle(PrintfulPlugin::getInstance()->getSettings()->productType);
        $fieldLayout = $pt->getVariantFieldLayout();

        $tabs = $fieldLayout->getTabs();
        $colorField = false;
        $sizeField = false;
        foreach ($tabs[0]->elements as $i => $element) {
            if ($element instanceof CustomField && $element->getField()->handle === 'printfulAttrColor') {
                $colorField = true;
            }
            if ($element instanceof CustomField && $element->getField()->handle === 'printfulAttrSize') {
                $sizeField = true;
            }
        }

        $color = \Craft::$app->fields->getFieldByHandle('printfulAttrColor');
        if (!$colorField) {
            $newElements = [
                'type' => CustomField::class,
                'fieldUid' => $color->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElements]));

            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }

        $size = \Craft::$app->fields->getFieldByHandle('printfulAttrSize');
        if (!$sizeField) {
            $newElements = [
                'type' => CustomField::class,
                'fieldUid' => $size->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElements]));
            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }
    }

    /**
     * @throws PrintfulException
     * @throws \Throwable
     * @throws InvalidConfigException
     * @throws Exception
     * @throws PrintfulApiException
     */
    public static function runImport($products, $variants): string
    {
        $importSettings = PrintfulPlugin::getInstance()->getSettings()->importProductImages;
        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $productType = new ProductTypes();
        $pt = $productType->getProductTypeByHandle(PrintfulPlugin::getInstance()->getSettings()->productType);

        // FIND THE FOLDER
        $folder = \Craft::$app->volumes->getVolumeByHandle('printfulImages');
        $folderId = $folder->id;

        // BUILD THE SLUG FOR THE URL AND IMAGE
        $lower = StringHelper::toLowerCase($products['name']);
        $url = StringHelper::toKebabCase($lower);

        $vArray = [];
        $category = 0;
        $variantCount = 0;
        foreach ($variants as $variant) {
            $vr = $pr->get('/store/variants/' . $variant['id']);
            $category = $vr['main_category_id'];

            if ($importSettings) {
                $filename = $url . '-' . strtolower($vr['color']);
                $asset = Asset::find()->title(str_replace('-', ' ', $filename))->one();
                if (is_null($asset)) {
                    $variantImageId = FetchImages::fetchRemoteImage($vr['files'][1]['preview_url'], null, null, $folderId, $filename);
                } else {
                    $variantImageId = $asset->id;
                }
            }

            $variantElement = new Variant();
            $variantElement->title = $vr['name'];
            $variantElement->sku = $vr['sku'];
            $variantElement->price = $vr['retail_price'];
            $variantElement->hasUnlimitedStock = true;
            $variantElement->setFieldValue('printfulVariantId', $vr['external_id']);
            $variantElement->setFieldValue('printfulAttrColor', $vr['color']);
            $variantElement->setFieldValue('printfulAttrSize', $vr['size']);
            if ($importSettings) {
                $variantElement->setFieldValue('printfulVariantImage', [$variantImageId]);
            } else {
                $variantElement->setFieldValue('printfulVariantCdn', $vr['files'][1]['preview_url']);
            }

            $vArray[] = $variantElement;
            $variantCount++;
        }

        $categoryQuery = Category::find();
        $categoryQuery->printfulCategoryId = $category;
        $getCategory = $categoryQuery->one();

        if ($importSettings) {
            $asset = Asset::find()->title(str_replace('-', ' ', $url))->one();
            if (is_null($asset)) {
                $productImageId = FetchImages::fetchRemoteImage($products['thumbnail_url'], null, null, $folderId, $url);
            } else {
                $productImageId = $asset->id;
            }
        }

        $product = Product::find()->title($products['name'])->one();

        if (is_null($product)) {
            $product = new Product();
        }

        $product->title = $products['name'];
        $product->name = $products['name'];
        $product->isNewForSite = true;
        $product->promotable = false;
        $product->typeId = $pt->id;
        $product->slug = $url;
        $product->freeShipping = true;
        $product->setFieldValue('printfulCategories', [$getCategory->id]);
        if ($importSettings) {
            $product->setFieldValue('printfulImage', [$productImageId]);
        } else {
            $product->setFieldValue('printfulCdnImage', $products['thumbnail_url']);
        }
        $product->setVariants($vArray);

        try {
            if (!\Craft::$app->elements->saveElement($product)) {
                $errors = $product->getFirstError();
            }
        } catch (ElementNotFoundException|Exception $e) {
            $errors =  $e->getMessage();
        } catch (\Throwable $e) {
            $errors =  $e->getMessage();
        }

        $plugin = PrintfulPlugin::getInstance();
        \Craft::$app->plugins->savePluginSettings($plugin, [
            'variantsImport' => $variantCount
        ]);

        if (!empty($errors)) {
            return $errors;
        } else {
            return 'ok';
        }
    }
}
