# Printful API for Craft CMS & Craft Commerce

This plugin allows you to add your products from your Printful account to your Craft Commerce website.

Key Features:

* API Integration with Printful.
* Import products, their variants and their images.
* Import and add the categories from Printful.
* Automatically create Order Statuses in Craft Commerce that meet Printful's requirements.
* Automatic shipping costs added to your cart (Craft Commerce Pro required).
* Easy access to reporting directly from Printful.
* Setup Webhooks to complete the following:
    * Package Shipped: When a package is ready to go, Printful will update your website and change your customer's order status to "Dispatched".
    * Package Returned: If you had to return a product, then this hook will update your customer's account to state that this has been returned.
    * Order Created: Once the order has been created, the webhook from Printful will update the customer's account to reflect this.
    * Order Updated: Any changes to your customer's order will be sent back to your Craft site. This will update your customer's order status to reflect the changes.
    * Order Failed: If the order has failed, then this will reflect as "Failed" on your customer's order. It might be worth contacting your customer if this happens.
    * Order Cancelled: If the order has been cancelled, this too will update your customer's order status to "Cancelled".
    * There are more webhooks coming soon and these will be released in the next minor update.
* Automatic shipping updates based on Webhooks.
* ... and lots more!

Turn your Craft Commerce website into an automated e-commerce system. Let this plugin do all the hard work for you by managing your orders, products and more. You don't need to do anything except import new products, update your website, and provide the best Customer Service that you can.

## Requirements

This plugin requires the following: - 
* Craft CMS 4.5.0 or later,
* Craft Commerce 4.3.0 or later,
* Printful API SDK 2.0 or later,
* PHP 8.0.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Printful API”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require atcipher-group/craft-printful-api

# tell Craft to install the plugin
./craft plugin/install printful-api
```
