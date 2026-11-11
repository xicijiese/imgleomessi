<?php
// 只用于本地测试站核验实际运行版本。
require_once '/wordpress/wp-load.php';
header('Content-Type: application/json');
echo wp_json_encode(array('wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'report' => get_option('ma_test_report'), 'post_id' => get_option('ma_test_post')));
