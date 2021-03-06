ActiveCampaign
===============

Wrapper on the ActiveCampaign PHP API - with custom methods and support for Laravel 5.x, and extension of Gentor's good work.

Installation
------------

Installation using composer:

```
composer require ghattrell/activecampaign
```


Add the service provider in `config/app.php`:

```php
Ghattrell\ActiveCampaign\ActiveCampaignServiceProvider::class,
```

Add the facade alias in `config/app.php`:

```php
'AC' => Ghattrell\ActiveCampaign\Facades\ActiveCampaign::class,
```

Configuration
-------------

Change your default settings in `app/config/activecampaign.php`:

```php
<?php
return [
    'api_url' => env('ACTIVECAMPAIGN_API_URL', '****'),
    'api_key' => env('ACTIVECAMPAIGN_API_KEY', '********'),
];
```


Documentation
-------------

[ActiveCampaign PHP API](https://github.com/ActiveCampaign/activecampaign-api-php)

[API Methods Overview](http://www.activecampaign.com/api/overview.php)

