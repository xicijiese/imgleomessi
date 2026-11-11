<?php
// 仅供本地兼容测试，不能打包或安装到正式网站。
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, 'https://wordpress.org/api/wordpress/v1/') !== 0) {
        return $pre;
    }
    $status = (int) get_option('ma_test_status', 200);
    if ($status !== 200) {
        return array('headers' => array(), 'response' => array('code' => $status), 'body' => '{}');
    }
    if (strpos($url, '/health') !== false) {
        $body = array('version' => 1);
    } elseif (strpos($url, '/resolve') !== false) {
        $body = array('album' => array('id' => 77));
    } else {
        parse_str(wp_parse_url($url, PHP_URL_QUERY), $query);
        $page = isset($query['page']) ? (int) $query['page'] : 1;
        $photo = array('id' => 'photo-'.$page, 'title' => '比赛图片 '.$page, 'caption' => '测试说明 '.$page,
            'alt' => '梅西比赛图片', 'thumbnail_url' => site_url('/wp-content/ma-tests/sample.svg?image='.$page),
            'display_url' => site_url('/wp-content/ma-tests/sample.svg?large='.$page));
        $body = array('album' => array('id' => 77, 'title' => '测试比赛相册', 'url' => 'https://wordpress.org/albums/test'),
            'revision' => 'fixture-version-1', 'photos' => array($photo), 'meta' => array('page' => $page, 'last_page' => 2, 'total' => 2));
    }
    return array('headers' => array(), 'response' => array('code' => 200), 'body' => wp_json_encode($body));
}, 10, 3);

// 旧版本环境验证经典编辑器，最新版本保留区块编辑器。
add_filter('use_block_editor_for_post', function ($use) {
    return version_compare($GLOBALS['wp_version'], '6.5', '<') ? false : $use;
});
