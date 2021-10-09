<?php
namespace App;

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;
use App\Console\ProcessRequest;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');
\Sentry\init(['dsn' => $_ENV['SENTRY_DSN'] ]);

$app = new Application();

// ... register commands
$app->add(new ProcessRequest());
$app->run();