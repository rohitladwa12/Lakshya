<?php
/**
 * Database Configuration
 * Handles database connection using PDO
 */

class Database {
    private static $instance = null;
    private $connections = [];
    
    // Connection definitions
    private $definitions = [];

    private function __construct() {
        // Local Portal DB (Default)
        $this->definitions['default'] = [
            'host' => 'localhost',
            'port' => '3306',
            'name' => 'placement_portal_v2',
            'user' => 'root',
            'pass' => ''
        ];

    //$this->definitions['gmu'] = [
      //  'host' => 'localhost',
       // 'port' => '3306',
       // 'name' => 'gmu',
       // 'user' => 'root',
       // 'pass' => ''
   //];

   //$this->definitions['gmit'] = [
     //   'host' => 'localhost',
       // 'port' => '3306',
     //   'name' => 'gmit_new',
     //   'user' => 'root',
     //   'pass' => ''
  //];

   $this->definitions['gmu'] = [
       'host' => '192.168.8.140',  // Since GMU tables are on localhost
	  	'user' => 'gmu_leap',     // MySQL username
	 	'pass' => '$ecure@ccess@LEAP', // MySQL password
	 	'name' => 'gmu',     // GMU database name
	 	'port' => 3306, 
    ];

   $this->definitions['gmit'] = [
      'host' => '192.168.8.140',  // Since GMU tables are on localhost
	  	'user' => 'gmu_leap',     // MySQL username
	  	'pass' => '$ecure@ccess@LEAP', // MySQL password
	  	'name' => 'gmit_new',     // GMU database name
	  	'port' => 3306, 
   ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection($name = 'default') {
        if (!isset($this->connections[$name])) {
            try {
                $this->connect($name);
            } catch (Exception $e) {
                return null;
            }
        }
        return $this->connections[$name] ?? null;
    }
    
    private function connect($name) {
        if (!isset($this->definitions[$name])) {
            throw new Exception("Database connection '{$name}' not defined.");
        }

        $config = $this->definitions[$name];

        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connections[$name] = new PDO($dsn, $config['user'], $config['pass'], $options);
            
        } catch (PDOException $e) {
            $this->handleConnectionError($e, $name);
        }
    }
    
    private function handleConnectionError($exception, $name) {
        error_log("Database Connection Error [{$name}]: " . $exception->getMessage());
        
        // Only die if it's the default connection or critical
        if ($name === 'default') {
            http_response_code(500);
            die("<h1>Database Error</h1><p>Failed to connect to the main database ({$name}).</p><p>" . htmlspecialchars($exception->getMessage()) . "</p>");
        }
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}

// Helper function to get database connection
function getDB($connectionName = 'default') {
    return Database::getInstance()->getConnection($connectionName);
}
