<?php
// use Dotenv\Dotenv;

// if(file_exists(__DIR__ . '../.env')){   
//     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
//     $dotenv->load();
// }

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

db()->connect([
    'dbtype' => $_SERVER['DB_TYPE'],
    'port' => null,
    'host' => $_SERVER['DB_HOST'],
    'username' => $_SERVER['DB_USERNAME'],
    'password' => $_SERVER['DB_PASSWORD'],
    'dbname' => $_SERVER['DB_NAME'],
]);