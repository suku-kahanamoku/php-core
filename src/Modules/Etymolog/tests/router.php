<?php

declare(strict_types=1);
require dirname(__DIR__, 4).'/vendor/autoload.php';
require __DIR__.'/database.php';
testDatabase();
$_ENV['APP_ENV'] = 'development';
$_ENV['FRANCHISE_CODES'] = 'ety.test:etymolog,other.test:other';
$_ENV['INTERNAL_API_KEY'] = 'etymolog-integration-internal-key';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$module = str_starts_with($path, '/api/auth/') ? 'auth' : 'etymolog';
$_SERVER['SCRIPT_NAME'] = '/api/'.$module.'/index.php';
require dirname(__DIR__, 4).'/api/'.$module.'/index.php';
