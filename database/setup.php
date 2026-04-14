<?php
/**
 * Database Setup Script
 * Run this file to create the database and populate it with seed data
 */

require_once __DIR__ . '/../config/bootstrap.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Placement Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .step {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .step h3 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .step p {
            color: #666;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        
        .log {
            background: #1e1e1e;
            color: #dcdcdc;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .log-success {
            color: #4caf50;
        }
        
        .log-error {
            color: #f44336;
        }
        
        .log-info {
            color: #2196f3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Placement Portal Setup</h1>
            <p>Database Initialization & Configuration</p>
        </div>
        
        <div class="content">
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                $action = $_POST['action'];
                
                echo '<div class="log">';
                
                try {
                    $db = getDB();
                    
                    if ($action === 'create_schema') {
                        echo '<div class="log-info">📋 Creating database schema...</div>';
                        
                        // Read schema file
                        $schemaFile = __DIR__ . '/schema.sql';
                        if (!file_exists($schemaFile)) {
                            throw new Exception('Schema file not found: ' . $schemaFile);
                        }
                        
                        $sql = file_get_contents($schemaFile);
                        
                        // Split by semicolon and execute each statement
                        $statements = array_filter(array_map('trim', explode(';', $sql)));
                        
                        $successCount = 0;
                        foreach ($statements as $statement) {
                            if (empty($statement) || strpos($statement, '--') === 0) {
                                continue;
                            }
                            
                            try {
                                $db->exec($statement);
                                $successCount++;
                            } catch (PDOException $e) {
                                // Ignore "database exists" errors
                                if (strpos($e->getMessage(), 'database exists') === false) {
                                    echo '<div class="log-error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                }
                            }
                        }
                        
                        echo '<div class="log-success">✅ Schema created successfully! Executed ' . $successCount . ' statements.</div>';
                        echo '</div>';
                        echo '<div class="alert alert-success"><strong>Success!</strong> Database schema has been created.</div>';
                        
                    } elseif ($action === 'seed_data') {
                        echo '<div class="log-info">🌱 Seeding database with sample data...</div>';
                        
                        // Read seed file
                        $seedFile = __DIR__ . '/seed_data.sql';
                        if (!file_exists($seedFile)) {
                            throw new Exception('Seed file not found: ' . $seedFile);
                        }
                        
                        $sql = file_get_contents($seedFile);
                        
                        // Split and execute
                        $statements = array_filter(array_map('trim', explode(';', $sql)));
                        
                        $successCount = 0;
                        foreach ($statements as $statement) {
                            if (empty($statement) || strpos($statement, '--') === 0) {
                                continue;
                            }
                            
                            try {
                                $db->exec($statement);
                                $successCount++;
                            } catch (PDOException $e) {
                                echo '<div class="log-error">⚠️ Warning: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                        }
                        
                        echo '<div class="log-success">✅ Seed data inserted successfully! Executed ' . $successCount . ' statements.</div>';
                        echo '</div>';
                        echo '<div class="alert alert-success"><strong>Success!</strong> Sample data has been added to the database.</div>';
                        
                    } elseif ($action === 'reset_database') {
                        echo '<div class="log-info">🔄 Resetting database...</div>';
                        
                        // Drop and recreate database
                        $dbName = getenv('DB_NAME') ?: 'placement_portal_v2';
                        
                        echo '<div class="log-info">Dropping database if exists...</div>';
                        $db->exec("DROP DATABASE IF EXISTS {$dbName}");
                        
                        echo '<div class="log-info">Creating fresh database...</div>';
                        $db->exec("CREATE DATABASE {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $db->exec("USE {$dbName}");
                        
                        echo '<div class="log-success">✅ Database reset complete!</div>';
                        echo '</div>';
                        echo '<div class="alert alert-success"><strong>Success!</strong> Database has been reset. Now run "Create Schema" and "Seed Data".</div>';
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="log-error">❌ Fatal Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    echo '</div>';
                    echo '<div class="alert alert-error"><strong>Error!</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                ?>
                <div class="alert alert-info">
                    <strong>ℹ️ Information:</strong> This setup wizard will help you initialize the database for the Placement Portal.
                </div>
                
                <div class="step">
                    <h3><span class="step-number">1</span> Create Database Schema</h3>
                    <p>Creates all 25 tables with proper structure, foreign keys, and indexes.</p>
                </div>
                
                <div class="step">
                    <h3><span class="step-number">2</span> Seed Sample Data</h3>
                    <p>Populates the database with sample users, companies, jobs, internships, and learning content for testing.</p>
                </div>
                
                <div class="step">
                    <h3><span class="step-number">3</span> Reset Database (Optional)</h3>
                    <p>⚠️ <strong>Warning:</strong> This will delete all existing data and reset the database to empty state.</p>
                </div>
                
                <div class="alert alert-warning">
                    <strong>⚠️ Default Credentials:</strong><br>
                    • Admin: <code>admin</code> / <code>admin123</code><br>
                    • Placement Officer: <code>placement_officer</code> / <code>placement123</code><br>
                    • Internship Officer: <code>internship_officer</code> / <code>internship123</code><br>
                    • Students: <code>student1-5</code> / <code>student123</code>
                </div>
                
                <form method="POST">
                    <div class="button-group">
                        <button type="submit" name="action" value="create_schema" class="btn btn-success">
                            📋 Create Schema
                        </button>
                        <button type="submit" name="action" value="seed_data" class="btn btn-success">
                            🌱 Seed Data
                        </button>
                        <button type="submit" name="action" value="reset_database" class="btn btn-danger" 
                                onclick="return confirm('Are you sure? This will delete ALL data!')">
                            🔄 Reset Database
                        </button>
                    </div>
                </form>
                <?php
            }
            ?>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999;">
                <p>Placement Portal v2.0 | Database Setup Wizard</p>
            </div>
        </div>
    </div>
</body>
</html>
