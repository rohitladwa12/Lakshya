<?php
/**
 * Legacy Redirect for casp_undertaking.php -> casp_opt_out_undertaking.php
 */
require_once __DIR__ . '/../../config/bootstrap.php';

$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header("Location: casp_opt_out_undertaking.php" . $qs, true, 301);
exit;
