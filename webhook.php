<?php
require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use TelegramBot\TelegramBot;
use App\App;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$bot = new TelegramBot($_ENV['BOT_TOKEN'], $_ENV['BOT_DOMAIN']);
$update = $bot->getWebhookUpdate();

$app = new App($bot);
$app->processUpdate();