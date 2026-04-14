<?php

namespace App\Helpers;

/**
 * SessionFilterHelper
 * Manages persisted filter states across page navigations to ensure clean URLs.
 */
class SessionFilterHelper {
    
    /**
     * Store filter data in the session for a specific module.
     */
    public static function setFilters($module, $data) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['filters'][$module] = $data;
    }

    /**
     * Retrieve stored filter data for a specific module.
     */
    public static function getFilters($module) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['filters'][$module] ?? [];
    }

    /**
     * Update/Merge partial filter data.
     */
    public static function updateFilters($module, $newData) {
        $existing = self::getFilters($module);
        self::setFilters($module, array_merge($existing, $newData));
    }

    /**
     * Clear filters for a specific module or resetting completely.
     */
    public static function clearFilters($module = null) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($module) {
            unset($_SESSION['filters'][$module]);
        } else {
            $_SESSION['filters'] = [];
        }
    }

    /**
     * Helper to merge POST into Session and redirect to clean URL.
     * Use this at the top of a controller/page.
     */
    public static function handlePostToSession($module, $postData, $redirectUrl = null) {
        if (!empty($postData)) {
            // Check if it's a reset action
            if (isset($postData['reset_filters'])) {
                self::clearFilters($module);
            } else {
                self::setFilters($module, $postData);
            }
            
            if ($redirectUrl) {
                header("Location: " . $redirectUrl);
                exit;
            }
            return true;
        }
        return false;
    }
}
