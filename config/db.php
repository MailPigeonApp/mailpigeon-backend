<?php
use Dotenv\Dotenv;

if(file_exists(__DIR__ . '../.env')){   
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
    $dotenv->load();
}

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
// $dotenv->load();

db()->connect([
    'dbtype' => $_ENV['DB_TYPE'],
    'port' => null,
    'host' => $_ENV['DB_HOST'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'dbname' => $_ENV['DB_NAME'],
]);