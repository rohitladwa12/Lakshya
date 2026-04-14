<?php
/**
 * RedisHelper.php
 * Centralized Redis connection and utility class.
 */

namespace App\Helpers;

use Predis\Client;
use Exception;

class RedisHelper {
    private static $instance = null;
    private $client;

    private function __construct() {
        // Load config if needed, or use defaults
        $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
        $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
        $password = defined('REDIS_PASSWORD') ? REDIS_PASSWORD : null;

        try {
            $this->client = new Client([
                'scheme'   => 'tcp',
                'host'     => $host,
                'port'     => $port,
                'password' => $password,
            ]);
            $this->client->connect();
        } catch (Exception $e) {
            error_log("Redis Connection Failed: " . $e->getMessage());
            $this->client = null;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getClient() {
        return $this->client;
    }

    public function isConnected() {
        return $this->client !== null;
    }

    /**
     * Set a value with TTL
     */
    public function set($key, $value, $ttl = 3600) {
        if (!$this->isConnected()) return false;
        try {
            $this->client->setex($key, $ttl, serialize($value));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get a value
     */
    public function get($key) {
        if (!$this->isConnected()) return null;
        try {
            $data = $this->client->get($key);
            return $data ? unserialize($data) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Delete a key
     */
    public function delete($key) {
        if (!$this->isConnected()) return false;
        try {
            $this->client->del([$key]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
