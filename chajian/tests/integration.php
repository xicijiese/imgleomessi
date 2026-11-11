<?php
// 在真实 WordPress 核心中测试公开操作，接口由本地 fixture 拦截。
require_once '/wordpress/wp-load.php';
require_once ABSPATH.'wp-admin/includes/template.php';
$checks = 0;
function ma_expect($condition, $message)
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
$plugin = new Messi_Album_Plugin();
update_option('messi_album_options', array('url' => 'https://wordpress.org', 'token' => str_repeat('a', 64)));
wp_set_current_user(1);
$post_id = wp_insert_post(array('post_title' => '相册兼容测试', 'post_status' => 'publish', 'post_content' => '[messi_album ref="testref"]'));
update_option('ma_test_post', $post_id);
update_post_meta($post_id, Messi_Album_Plugin::META, array('testref' => array('album_id' => 77, 'title' => '测试比赛相册', 'columns' => 3, 'captions' => true,
    'photos' => array(array('id' => 'photo-2', 'caption' => '<script>不会执行</script>'), array('id' => 'photo-1', 'caption' => '文章独立说明')))));
$GLOBALS['post'] = get_post($post_id);
$before = (int) wp_count_posts('attachment')->inherit;
$html = $plugin->render(array('ref' => 'testref'));
ma_expect(substr_count($html, 'class="ma-photo"') === 2, '读取完整分页');
ma_expect(strpos($html, 'sample.svg?image=2') < strpos($html, 'sample.svg?image=1'), '保留文章排列');
ma_expect(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, '转义说明');
ma_expect(strpos($html, 'sample.svg?large=2') !== false, '复用远程展示图');
ma_expect((int) wp_count_posts('attachment')->inherit === $before, '不创建附件');
ma_expect(strpos($plugin->render(array('ref' => 'absent')), '不可用') !== false, '无效引用');
$old = get_option('messi_album_options');
ma_expect($plugin->sanitize_options(array('url' => 'https://wordpress.org', 'token' => ''))['token'] === $old['token'], '空密码保留');
ma_expect($plugin->sanitize_options(array('url' => 'http://evil.test', 'token' => '')) === $old, '拒绝非HTTPS地址');

// 缓存通过后模拟下架、短暂故障和过期，验证用户实际看到的结果。
global $wpdb;
$transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_ma_%'");
$cache_key = '';
foreach ($transients as $name) {
    if (substr($name, -6) !== '_retry') {
        $cache_key = substr($name, strlen('_transient_'));
    }
}
ma_expect($cache_key !== '', '存在公开核验缓存');
$cached = get_transient($cache_key);
$cached['checked'] = time() - 301;
set_transient($cache_key, $cached, 900);
update_option('ma_test_status', 503);
ma_expect(substr_count($plugin->render(array('ref' => 'testref')), 'class="ma-photo"') === 2, '短暂故障有限使用缓存');
$cached['checked'] = time() - 901;
set_transient($cache_key, $cached, 900);
ma_expect(strpos($plugin->render(array('ref' => 'testref')), '暂不可用') !== false, '过期核验失败隐藏');
delete_transient($cache_key.'_retry');
$cached['checked'] = time() - 301;
set_transient($cache_key, $cached, 900);
update_option('ma_test_status', 404);
ma_expect(strpos($plugin->render(array('ref' => 'testref')), '暂不可用') !== false, '明确下架立即隐藏');
ma_expect(get_transient($cache_key) === false, '清除下架缓存');
update_option('ma_test_status', 200);
delete_transient($cache_key.'_retry');

// 恢复两张选图用于浏览器验收。
update_post_meta($post_id, Messi_Album_Plugin::META, array('testref' => array('album_id' => 77, 'title' => '测试比赛相册', 'columns' => 3, 'captions' => true,
    'photos' => array(array('id' => 'photo-2', 'caption' => '第二张图片说明'), array('id' => 'photo-1', 'caption' => '文章独立说明')))));
$report = array('wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'checks' => $checks, 'post_id' => $post_id);
update_option('ma_test_report', $report);
echo wp_json_encode($report);
