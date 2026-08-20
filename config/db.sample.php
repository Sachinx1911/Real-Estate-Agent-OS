<?php
/**
 * RE360 — Hostinger database credentials template.
 *
 * HOW TO USE
 * 1. hPanel  ->  Databases  ->  MySQL Databases  ->  create database + user
 * 2. Copy this file to  config/db.local.php  (File Manager -> Copy / Rename)
 * 3. Fill in the four values below and save
 *
 * config/db.php loads db.local.php automatically when it exists, so your
 * real password never has to be committed to git.
 */

$DB_HOST    = 'localhost';          // Hostinger shared hosting: almost always 'localhost'
$DB_PORT    = 3306;
$DB_NAME    = 'u123456789_re360';   // hPanel मध्ये दिसणारे database name
$DB_USER    = 'u123456789_re360';   // database user
$DB_PASS    = 'PUT-YOUR-PASSWORD-HERE';
$DB_CHARSET = 'utf8mb4';
