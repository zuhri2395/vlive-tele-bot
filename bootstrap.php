<?php
// bootstrap.php
require_once "vendor/autoload.php";

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Dotenv\Dotenv;

// Create a simple "default" Doctrine ORM configuration for Annotations
$isDevMode = true;
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/src/Entity/"), $isDevMode, null, null, false);

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// database configuration parameters
$conn = array(
    'driver' => 'pdo_mysql',
    'host' => $_ENV['DB_HOST'],
    'user'     => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS'],
    'dbname'   => $_ENV['DB_NAME']
);

// obtaining the entity manager
$entityManager = EntityManager::create($conn, $config);