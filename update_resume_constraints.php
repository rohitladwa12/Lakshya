<?php
require_once 'c:/xampp/htdocs/Lakshya/config/database.php';
$mysqli = getDB('default');

echo "Updating unique constraint for generated_resumes...\n";

try {
    // Drop existing unique index on username
    try {
        $mysqli->exec("ALTER TABLE generated_resumes DROP INDEX username");
    } catch (Exception $e) {
        echo "No username index found or already dropped.\n";
    }

    // Add composite unique index
    $mysqli->exec("ALTER TABLE generated_resumes ADD UNIQUE INDEX idx_user_inst (username, institution)");
    echo "Added composite unique index (username, institution).\n";
} catch (Exception $e) {
    echo "Error updating constraints: " . $e->getMessage() . "\n";
}
?>
