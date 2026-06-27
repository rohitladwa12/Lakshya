<?php
require 'c:\htdocs\Lakshya\config\bootstrap.php';
try {
    getDB()->exec('ALTER TABLE job_postings MODIFY application_deadline DATETIME NULL');
    echo "job_postings altered successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
