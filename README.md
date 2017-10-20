# spry-log
Default Logger Class for Spry

### Requires
* PHP 5.4^
* Spry-Core https://github.com/ggedde/spry-core

### Usage

Use it through Spry as a Provider.

This allows you to swap out the Provider later on without having to change your project code.

```php
Spry::log()->message("My Message");
Spry::log()->warning("Warning");
Spry::log()->error("Error");
```
OR 

Use as a standalone Class.  (still requires Spry-Core)

```php
 SpryLog::message("My Message");
```

### Spry Configuration

```php
$config->logger = 'Spry\\SpryProvider\\SpryLog';
$config->log_format = '%date_time% %ip% %path% - %msg%';
$config->log_php_file = __DIR__.'/logs/php.log';
$config->log_api_file = __DIR__.'/logs/api.log';
$config->log_max_lines = 5000;
$config->log_archive = false;
$config->log_prefix = [
	'message' => 'Spry: ',
	'warning' => 'Spry Warning: ',
	'error' => 'Spry ERROR: ',
	'stop' => 'Spry STOPPED: ',
	'response' => 'Spry Response: ',
	'request' => 'Spry Request: '
];
```

### Available Methods for Spry Hooks
* setup_php_logs()
* request()

### Available Methods for Spry Filters
* response($response)
* stop($params)
