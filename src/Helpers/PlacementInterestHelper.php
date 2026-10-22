<?php
namespace App\Helpers;

/**
 * PlacementInterestHelper
 * Tracks student placement interest (Yes vs No) without creating new database tables.
 * Persists data in D:/LakshyaUploads/casp_undertakings/placement_intent.json and dedicated subfolders:
 * - D:/LakshyaUploads/casp_undertakings/opt_out/OPT_OUT_{USN}_Undertaking.pdf
 * - D:/LakshyaUploads/casp_undertakings/policy/POLICY_{USN}_Undertaking.pdf
 */
class PlacementInterestHelper
{
    private static string $storageDir = 'D:/LakshyaUploads/casp_undertakings';
    private static string $optOutDir = 'D:/LakshyaUploads/casp_undertakings/opt_out';
    private static string $policyDir = 'D:/LakshyaUploads/casp_undertakings/policy';
    private static string $registryFile = 'D:/LakshyaUploads/casp_undertakings/placement_intent.json';

    public static function getStorageDir(): string
    {
        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0777, true);
        }
        return self::$storageDir;
    }

    public static function getOptOutDir(): string
    {
        self::getStorageDir();
        if (!is_dir(self::$optOutDir)) {
            @mkdir(self::$optOutDir, 0777, true);
        }
        return self::$optOutDir;
    }

    public static function getPolicyDir(): string
    {
        self::getStorageDir();
        if (!is_dir(self::$policyDir)) {
            @mkdir(self::$policyDir, 0777, true);
        }
        return self::$policyDir;
    }

    private static function getRegistryPath(): string
    {
        self::getStorageDir();
        return self::$registryFile;
    }

    /**
     * Get all records from JSON registry
     */
    public static function getAll(): array
    {
        $path = self::getRegistryPath();
        if (!file_exists($path)) {
            return [];
        }
        $content = @file_get_contents($path);
        if (!$content) {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Resolve file path for Opt-Out PDF
     */
    public static function getOptOutPdfPath(string $usn): string
    {
        $safeUsn = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtoupper(trim($usn)));
        $preferred = self::getOptOutDir() . '/OPT_OUT_' . $safeUsn . '_Undertaking.pdf';
        if (file_exists($preferred)) {
            return $preferred;
        }
        // Legacy root paths
        $legacy1 = self::$storageDir . '/' . $safeUsn . '_CASP_Undertaking.pdf';
        if (file_exists($legacy1)) {
            return $legacy1;
        }
        $legacy2 = self::$storageDir . '/OPT_OUT_' . $safeUsn . '_Undertaking.pdf';
        if (file_exists($legacy2)) {
            return $legacy2;
        }
        return $preferred;
    }

    /**
     * Resolve file path for Placement Policy PDF
     */
    public static function getPolicyPdfPath(string $usn): string
    {
        $safeUsn = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtoupper(trim($usn)));
        $preferred = self::getPolicyDir() . '/POLICY_' . $safeUsn . '_Undertaking.pdf';
        if (file_exists($preferred)) {
            return $preferred;
        }
        $legacy = self::$storageDir . '/POLICY_' . $safeUsn . '_Undertaking.pdf';
        if (file_exists($legacy)) {
            return $legacy;
        }
        return $preferred;
    }

    public static function hasOptOutPdf(string $usn): bool
    {
        $path = self::getOptOutPdfPath($usn);
        return file_exists($path) && filesize($path) > 0;
    }

    public static function hasPolicyPdf(string $usn): bool
    {
        $path = self::getPolicyPdfPath($usn);
        return file_exists($path) && filesize($path) > 0;
    }

    /**
     * Check if a student has opted out (cannot opt in)
     */
    public static function isOptedOut(string $usn): bool
    {
        $usn = strtoupper(trim($usn));
        if ($usn === '') return false;

        $hasOpt = self::hasOptOutPdf($usn);
        $hasPol = self::hasPolicyPdf($usn);
        if ($hasOpt && $hasPol) {
            $optTime = @filemtime(self::getOptOutPdfPath($usn)) ?: 0;
            $polTime = @filemtime(self::getPolicyPdfPath($usn)) ?: 0;
            return $optTime <= $polTime;
        }

        if ($hasOpt) {
            return true;
        }

        $all = self::getAll();
        if (isset($all[$usn])) {
            return !empty($all[$usn]['opt_out']) || (isset($all[$usn]['interested']) && $all[$usn]['interested'] === false);
        }

        return false;
    }

    /**
     * Check if a student has opted in / signed placement policy (cannot opt out)
     */
    public static function isOptedIn(string $usn): bool
    {
        $usn = strtoupper(trim($usn));
        if ($usn === '') return false;

        $hasOpt = self::hasOptOutPdf($usn);
        $hasPol = self::hasPolicyPdf($usn);
        if ($hasOpt && $hasPol) {
            $optTime = @filemtime(self::getOptOutPdfPath($usn)) ?: 0;
            $polTime = @filemtime(self::getPolicyPdfPath($usn)) ?: 0;
            return $polTime < $optTime;
        }

        if ($hasPol) {
            return true;
        }

        $all = self::getAll();
        if (isset($all[$usn])) {
            return !empty($all[$usn]['interested']) && empty($all[$usn]['opt_out']);
        }

        return false;
    }

    /**
     * Can student opt in to placement?
     * Strictly false if already opted out.
     */
    public static function canOptIn(string $usn): bool
    {
        return !self::isOptedOut($usn);
    }

    /**
     * Can student opt out of placement?
     * Strictly false if already opted in / signed policy.
     */
    public static function canOptOut(string $usn): bool
    {
        return !self::isOptedIn($usn);
    }

    /**
     * Check if a student has already made a decision (asked once)
     */
    public static function hasDecision(string $usn): bool
    {
        $usn = strtoupper(trim($usn));
        if ($usn === '') return false;

        return self::isOptedOut($usn) || self::isOptedIn($usn);
    }

    /**
     * Get student decision: returns true (opted-in), false (opted-out), or null (not decided yet)
     */
    public static function getDecision(string $usn): ?bool
    {
        $usn = strtoupper(trim($usn));
        if ($usn === '') return null;

        if (self::isOptedOut($usn)) {
            return false;
        }
        if (self::isOptedIn($usn)) {
            return true;
        }

        return null;
    }

    /**
     * Save student decision with strict mutual exclusion:
     * Once student opts out, they cannot opt in.
     * Once student opts in, they cannot opt out.
     */
    public static function setDecision(string $usn, bool $interested, array $meta = []): bool
    {
        $usn = strtoupper(trim($usn));
        if ($usn === '') return false;

        // Strict Mutual Exclusion: Prevent flipping once a decision is made
        if ($interested && self::isOptedOut($usn)) {
            error_log("PlacementInterestHelper: Blocked opt-in attempt for already opted-out student {$usn}");
            return false;
        }
        if (!$interested && self::isOptedIn($usn)) {
            error_log("PlacementInterestHelper: Blocked opt-out attempt for already opted-in student {$usn}");
            return false;
        }

        $all = self::getAll();
        $all[$usn] = array_merge([
            'interested' => $interested,
            'opt_out' => !$interested,
            'status' => $interested ? 'interested' : 'opt_out',
            'updated_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ], $meta);

        $path = self::getRegistryPath();
        return (bool)@file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT));
    }

    /**
     * Get comprehensive map of all students (both Opt-Out and Policy signed)
     */
    public static function getStatusMap(): array
    {
        $map = [];

        // 1. Load registry
        $all = self::getAll();
        foreach ($all as $u => $item) {
            $u = strtoupper(trim($u));
            $isOptedOut = isset($item['interested']) && $item['interested'] === false;
            $map[$u] = [
                'interested' => !$isOptedOut,
                'opt_out' => $isOptedOut,
                'has_opt_out_pdf' => false,
                'has_policy_pdf' => false,
                'opt_out_file' => null,
                'policy_file' => null,
                'updated_at' => $item['updated_at'] ?? ''
            ];
        }

        // 2. Scan Opt-Out directory & legacy
        $optOutFiles = array_merge(
            (array)@glob(self::getOptOutDir() . '/*_Undertaking.pdf'),
            (array)@glob(self::$storageDir . '/OPT_OUT_*_Undertaking.pdf'),
            (array)@glob(self::$storageDir . '/*_CASP_Undertaking.pdf')
        );
        foreach ($optOutFiles as $f) {
            if (!$f || !file_exists($f)) continue;
            $base = basename($f);
            $u = null;
            if (preg_match('/^OPT_OUT_([^_]+)_Undertaking\.pdf$/i', $base, $m)) {
                $u = strtoupper($m[1]);
            } elseif (preg_match('/^([^_]+)_CASP_Undertaking\.pdf$/i', $base, $m)) {
                $u = strtoupper($m[1]);
            }
            if ($u) {
                if (!isset($map[$u])) {
                    $map[$u] = [
                        'interested' => false,
                        'opt_out' => true,
                        'has_opt_out_pdf' => true,
                        'has_policy_pdf' => false,
                        'opt_out_file' => $base,
                        'policy_file' => null,
                        'updated_at' => date('Y-m-d H:i:s', filemtime($f))
                    ];
                } else {
                    $map[$u]['opt_out'] = true;
                    $map[$u]['interested'] = false;
                    $map[$u]['has_opt_out_pdf'] = true;
                    $map[$u]['opt_out_file'] = $base;
                }
            }
        }

        // 3. Scan Policy directory
        $policyFiles = array_merge(
            (array)@glob(self::getPolicyDir() . '/*_Undertaking.pdf'),
            (array)@glob(self::$storageDir . '/POLICY_*_Undertaking.pdf')
        );
        foreach ($policyFiles as $f) {
            if (!$f || !file_exists($f)) continue;
            $base = basename($f);
            if (preg_match('/^POLICY_([^_]+)_Undertaking\.pdf$/i', $base, $m)) {
                $u = strtoupper($m[1]);
                if (!isset($map[$u])) {
                    $map[$u] = [
                        'interested' => true,
                        'opt_out' => false,
                        'has_opt_out_pdf' => false,
                        'has_policy_pdf' => true,
                        'opt_out_file' => null,
                        'policy_file' => $base,
                        'updated_at' => date('Y-m-d H:i:s', filemtime($f))
                    ];
                } else {
                    $map[$u]['has_policy_pdf'] = true;
                    $map[$u]['policy_file'] = $base;
                }
            }
        }

        // Mutual exclusion resolution for any legacy students with both documents
        foreach ($map as $u => &$item) {
            if (!empty($item['has_opt_out_pdf']) && !empty($item['has_policy_pdf'])) {
                $item['opt_out'] = self::isOptedOut($u);
                $item['interested'] = self::isOptedIn($u);
            }
        }
        unset($item);

        return $map;
    }

    /**
     * Legacy helper for backwards compatibility
     */
    public static function getOptOutMap(): array
    {
        $statusMap = self::getStatusMap();
        $optMap = [];
        foreach ($statusMap as $u => $item) {
            if (!empty($item['opt_out'])) {
                $optMap[$u] = [
                    'interested' => false,
                    'has_pdf' => !empty($item['has_opt_out_pdf']),
                    'pdf_file' => $item['opt_out_file'],
                    'updated_at' => $item['updated_at']
                ];
            }
        }
        return $optMap;
    }
}
