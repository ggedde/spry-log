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
 SpryLogger::message("My Message");
```

### Spry Configuration

```php
$config->loggerProvider = 'Spry\\SpryProvider\\SpryLogger';
$config->logger [
	'format' = '%date_time% %ip% %path% - %msg%',
    'php_format' => "%date_time% %errstr% %errfile% [Line: %errline%]\n%backtrace%",
	'php_file' => __DIR__.'/logs/php.log',
	'api_file' => __DIR__.'/logs/api.log',
	'max_lines' => 5000,
	'archive' => false,
	'prefix' => [
		'message' => 'Spry: ',
		'warning' => 'Spry Warning: ',
		'error' => 'Spry ERROR: ',
		'stop' => 'Spry STOPPED: ',
		'response' => 'Spry Response: ',
		'request' => 'Spry Request: ',
	]
];
```

### Available Methods for Spry Hooks
* setupPhpLogs()
* request()
* stop($params)

### Available Methods for Spry Filters
* response($response)
