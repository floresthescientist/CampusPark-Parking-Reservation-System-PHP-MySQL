<?php
/*
 * inc/connect.php
 * Opens the connection to MySQL using PDO.
 * Change the four settings below to match your computer.
 *   XAMPP: user "root", password ""      MAMP: user "root", password "root"
 */

$dbHost = 'localhost';
$dbName = 'campuspark';
$dbUser = 'root';
$dbPass = '';

$options = [
    // stop and throw an error if a query fails, instead of failing silently
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // give us rows as ['column' => value] arrays
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // send the query and the values to MySQL separately (real prepared statements)
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $error) {
    // write the real error to the PHP log, show the visitor something simple
    error_log('CampusPark DB connection failed: ' . $error->getMessage());
    http_response_code(500);
    exit('Sorry, CampusPark cannot reach the database right now. (Check inc/connect.php)');
}
