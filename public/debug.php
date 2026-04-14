<!DOCTYPE html>
<html>
<head>
    <title>Debug Info</title>
</head>
<body>
    <h1>Debug Information</h1>
    <p><strong>This file location:</strong> <?php echo __FILE__; ?></p>
    <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
    <p><strong>Current URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></p>
    <p><strong>Server Name:</strong> <?php echo $_SERVER['SERVER_NAME']; ?></p>
    
    <h2>File Checks:</h2>
    <p>login.php exists: <?php echo file_exists('login.php') ? 'YES' : 'NO'; ?></p>
    <p>officer_login.php exists: <?php echo file_exists('officer_login.php') ? 'YES' : 'NO'; ?></p>
    
    <h2>Test Links (Relative):</h2>
    <p><a href="login.php">login.php</a></p>
    <p><a href="officer_login.php">officer_login.php</a></p>
    
    <h2>Test Links (Absolute from root):</h2>
    <p><a href="/Lakshya/public/login.php">/Lakshya/public/login.php</a></p>
    <p><a href="/Lakshya/public/officer_login.php">/Lakshya/public/officer_login.php</a></p>
    
    <h2>All PHP files in this directory:</h2>
    <ul>
    <?php
    $files = glob('*.php');
    foreach($files as $file) {
        echo "<li>$file</li>";
    }
    ?>
    </ul>
</body>
</html>
