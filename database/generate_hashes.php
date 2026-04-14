<?php
/**
 * Password Hash Generator
 * Run this script to generate correct password hashes for seed data
 */

$passwords = [
    'admin123' => 'Admin password',
    'placement123' => 'Placement Officer password',
    'internship123' => 'Internship Officer password',
    'student123' => 'Student password'
];

echo "Password Hashes for Seed Data:\n";
echo str_repeat('=', 80) . "\n\n";

foreach ($passwords as $password => $description) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "{$description}:\n";
    echo "Password: {$password}\n";
    echo "Hash: {$hash}\n\n";
}

echo str_repeat('=', 80) . "\n";
echo "Copy these hashes to seed_data.sql\n";
