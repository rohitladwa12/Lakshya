<?php
require 'c:\htdocs\Lakshya\config\bootstrap.php';
try {
    getDB()->exec('ALTER TABLE internships MODIFY application_deadline DATETIME NULL');
    echo "internships altered successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
