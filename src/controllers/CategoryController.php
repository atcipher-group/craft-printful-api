<?php

namespace atciphergroup\craftprintfulapi\controllers;

use atciphergroup\craftprintfulapi\Plugin as PrintfulPlugin;
use craft\elements\Category;
use craft\errors\CategoryGroupNotFoundException;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\helpers\StringHelper;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\FieldGroup;
use craft\services\Fields;
use craft\services\Sites;
use craft\web\Controller;
use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CategoryController extends Controller
{
    public function actionSettings(): Response
    {
        $settings = PrintfulPlugin::getInstance()->getSettings();

        return $this->renderTemplate('craft-printful-api/settings/category.twig', [
            'settings' => $settings
        ]);
    }

    /**
     * @throws PrintfulException
     * @throws InvalidConfigException
     * @throws PrintfulApiException
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     * @throws CategoryGroupNotFoundException
     * @throws \Throwable
     */
    public function actionImport()
    {
        $this->requireAdmin();
        $this->requireCpRequest();
        $this->requirePostRequest();

        $apiKey = PrintfulPlugin::getInstance()->getSettings()->apiKey;
        $settings = [
            'fieldGroup' => false,
            'field' => false,
            'group' => false,
            'import' => 0,
            'datetime' => new \DateTime("now")
        ];

        $pr = new PrintfulApiClient($apiKey, 'oauth-token');

        $sites = new Sites();
        $siteIds = $sites->allSiteIds;

        $allFieldGroups = \Craft::$app->getFields()->getAllGroups();
        $fieldGroupCheck = null;
        foreach ($allFieldGroups as $row) {
            if ($row->name === 'Product') {
                $fieldGroupCheck = $row->id;
                $settings['fieldGroup'] = true;
            }
        }

        if (is_null($fieldGroupCheck)) {
            $fieldGroup = new FieldGroup();
            $fieldGroup->name = "Product";
            \Craft::$app->fields->saveGroup($fieldGroup);
            $fieldGroupCheck = null;
            foreach ($allFieldGroups as $row) {
                if ($row->name === 'Product') {
                    $fieldGroupCheck = $row->id;
                    $settings['fieldGroup'] = true;
                }
            }
        }

        $field = \Craft::$app->fields->getFieldByHandle('printfulCategoryId');
        $fields = new Fields();
        if (is_null($field)) {
            $field = $fields->createField([
                'name' => 'Printful Category ID',
                'handle' => 'printfulCategoryId',
                'type' => 'craft\fields\PlainText',
                'groupId' => $fieldGroupCheck,
                'searchable' => false,
            ]);
            \Craft::$app->fields->saveField($field);
            $field = \Craft::$app->fields->getFieldByHandle('printfulCategoryId');
        }

        $settings['field'] = true;

        $group = \Craft::$app->getCategories()->getGroupByHandle('productCategories');
        if (is_null($group)) {
            $categoryGroup = new CategoryGroup();
            $categoryGroup->name = 'Product Categories';
            $categoryGroup->handle = 'productCategories';
            $categoryGroup->defaultPlacement = 'end';
            $categoryGroup->siteSettings = [
                $siteIds[0] => new CategoryGroup_SiteSettings([
                    'hasUrls' => true,
                    'uriFormat' => 'shop/category/{slug}'
                ])
            ];
            \Craft::$app->categories->saveGroup($categoryGroup);
            $group = \Craft::$app->getCategories()->getGroupByHandle('productCategories');
        }

        $settings['group'] = true;

        // ADD A FIELD
        $fieldLayout = $group->getFieldLayout();
        $tabs = $fieldLayout->getTabs();
        $check = false;
        foreach ($tabs[0]->getLayout()->getCustomFields() as $i => $element) {
            if ($element instanceof PlainText && $element->handle === 'printfulCategoryId') {
                $check = true;
            }
        }

        if (!$check) {
            $newElement = [
                'type' => CustomField::class,
                'fieldUid' => $field->uid,
                'required' => false
            ];

            $tabs[0]->setElements(array_merge($tabs[0]->getElements(), [$newElement]));
            $fieldLayout->setTabs($tabs);
            \Craft::$app->fields->saveLayout($fieldLayout);
        }

        $categories = $pr->get('categories');
        $categoriesArray = [];
        foreach ($categories as $category) {
            foreach ($category as $row) {
                $categoriesArray[$row['id']] = [
                    'title' => $row['title'],
                    'parent_id' => $row['parent_id']
                ];
            }
        }

        $i = 0;
        foreach ($categories as $key => $value)
        {
            array_multisort(
                array_column($value, 'parent_id'),
                array_column($value, 'id'),
                $value
            );
            $empty = [];
            foreach ($value as $row) {
                $category = new Category();
                $category->title = $row['title'];
                $category->siteId = $siteIds[0];
                $category->groupId = $group->id;
                $category->printfulCategoryId = $row['id'];
                $lower = StringHelper::toLowerCase($row['title']);
                $slug = StringHelper::toKebabCase($lower);
                $category->slug = $slug;
                if ($row['parent_id'] > 0) {
                    if ($categoriesArray[$row['parent_id']]['parent_id'] !== 0) {
                        $mainParentCategory = Category::findOne([
                            'title' => $categoriesArray[$categoriesArray[$row['parent_id']]['parent_id']]['title'],
                            'level' => 1
                        ]);
                        $parentCategory = Category::findOne(['descendantOf' => $mainParentCategory->getId()]);
                        if (is_null($parentCategory)) {
                            $empty[] = $row;
                            continue;
                        } else {
                            $category->parentId = $parentCategory->getId();
                        }
                    } else {
                        $parentCategory = Category::findOne([
                            'title' => $categoriesArray[$row['parent_id']]['title'],
                            'level' => 1
                        ]);
                        $category->parentId = $parentCategory->getId();
                    }
                }

                $checkExists = Category::findOne(['slug' => $category->slug, 'printfulCategoryId' => $category->printfulCategoryId]);
                if (is_null($checkExists)) {
                    \Craft::$app->elements->saveElement($category);
                    $i++;
                }
                unset($category);
                unset($parentCategory);
            }

            array_multisort(
                array_column($empty, 'parent_id'),
                array_column($empty, 'id'),
                $empty
            );
            foreach ($empty as $row) {
                $category = new Category();
                $category->title = $row['title'];
                $category->siteId = $siteIds[0];
                $category->groupId = $group->id;
                $category->printfulCategoryId = $row['id'];
                $lower = StringHelper::toLowerCase($row['title']);
                $slug = StringHelper::toKebabCase($lower);
                $category->slug = $slug;

                $mainParentCategory = Category::findOne([
                    'title' => $categoriesArray[$categoriesArray[$row['parent_id']]['parent_id']]['title'],
                    'level' => 1
                ]);
                $parentCategory = Category::findOne(['descendantOf' => $mainParentCategory->getId()]);
                if (is_null($parentCategory)) {
                    continue;
                } else {
                    $category->parentId = $parentCategory->getId();
                }

                $checkExists = Category::findOne(['slug' => $category->slug]);
                if (is_null($checkExists)) {
                    \Craft::$app->elements->saveElement($category);
                    $i++;
                }
                unset($category);
                unset($parentCategory);
            }
        }

        $settings['import'] = $i;
        $settings['datetime'] = new \DateTime("now");

        $plugin = PrintfulPlugin::getInstance();
        \Craft::$app->plugins->savePluginSettings($plugin, [
            'categoryFieldGroup' => $settings['fieldGroup'],
            'categoryField' => $settings['field'],
            'categoryGroup' => $settings['group'],
            'categoryImport' => $settings['import'],
            'categoryImportDateTime' => $settings['datetime'],
        ]);
        $this->setSuccessFlash('You have successfully imported your categories');
    }
}