<?php

namespace atciphergroup\craftprintfulapi\models;

use craft\base\Model;

/**
 * Printful settings
 */
class Settings extends Model
{
    public string $apiKey = "";

    public string $allAccessKey = "";

    public string $productType = "";

    // CATEGORY SECTION
    public bool $categoryFieldGroup = false;

    public bool $categoryField = false;

    public bool $categoryGroup = false;

    public int $categoryImport = 0;

    public \DateTime|null $categoryImportDateTime = null;

    public bool $printfulShipping = true;

    public int $productImport = 0;

    public int $variantImport = 0;

    public \DateTime|null $productImportDateTime = null;

    public bool $importProductImages = false;
}
