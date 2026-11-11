<?php
/**
 * Plugin Name: 梅西图库相册
 * Description: 引用梅西影像档案库公开相册，不下载或重复上传图片。支持经典编辑器及短代码。
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: LeoMessi
 * Text Domain: messi-album
 */
if (! defined('ABSPATH')) {
    exit;
}

final class Messi_Album_Plugin
{
    const VERSION = '1.0.0';

    const META = '_messi_album_embeds';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('media_buttons', [$this, 'button']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('wp_ajax_messi_album', [$this, 'ajax']);
        add_action('wp_enqueue_scripts', [$this, 'front_assets']);
        add_shortcode('messi_album', [$this, 'render']);
    }

    public function menu()
    {
        add_options_page('梅西图库相册', '梅西图库相册', 'manage_options', 'messi-album', [$this, 'settings_page']);
    }

    public function settings()
    {
        register_setting('messi_album', 'messi_album_options', ['sanitize_callback' => [$this, 'sanitize_options']]);
    }

    public function sanitize_options($input)
    {
        $old = get_option('messi_album_options', []);
        $url = untrailingslashit(esc_url_raw(isset($input['url']) ? $input['url'] : '', ['https']));
        $parts = wp_parse_url($url);
        if (! $parts || empty($parts['host']) || ! empty($parts['user']) || ! empty($parts['pass'])
            || ! empty($parts['query']) || ! empty($parts['fragment']) || (! empty($parts['path']) && $parts['path'] !== '/')) {
            add_settings_error('messi_album', 'url', '请输入完整的 HTTPS 图库域名，不包含路径。');

            return $old;
        }
        $token = isset($input['token']) ? trim($input['token']) : '';
        if ($token !== '' && ! preg_match('/^[A-Za-z0-9_-]{32,200}$/D', $token)) {
            add_settings_error('messi_album', 'token', '令牌须为32至200位字母、数字、下划线或短横线。');

            return $old;
        }

        return ['url' => $url, 'token' => $token !== '' ? $token : (isset($old['token']) ? $old['token'] : '')];
    }

    public function settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $options = get_option('messi_album_options', []);
        ?>
        <div class="wrap"><h1>梅西图库相册</h1>
        <p>连接图库后，在文章中引用已有图片，不创建媒体附件或上传副本。</p>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('messi_album'); ?>
            <table class="form-table"><tr><th><label for="ma-url">图库地址</label></th><td>
                <input class="regular-text" type="url" id="ma-url" name="messi_album_options[url]" required value="<?php echo esc_attr(isset($options['url']) ? $options['url'] : 'https://img.leomessi.cn'); ?>">
            </td></tr><tr><th><label for="ma-token">只读令牌</label></th><td>
                <input class="regular-text" type="password" id="ma-token" name="messi_album_options[token]" value="" autocomplete="new-password">
                <p class="description">留空保留已保存令牌。仅保存在本站服务端；也可由 MESSI_ALBUM_TOKEN 常量提供。</p>
            </td></tr></table>
            <?php submit_button(); ?>
        </form><button type="button" class="button" id="ma-test">测试已保存的连接</button>
        <p id="ma-test-result" role="status" aria-live="polite"></p>
        <p>区块编辑器：先在文章的“图库相册”面板创建引用，再把生成的短代码粘贴到“短代码”区块。</p>
        <p>停用插件后相册将无法渲染，重新启用可恢复；不会删除图库图片或文章引用。</p></div>
        <?php
    }

    public function admin_assets($hook)
    {
        if (! in_array($hook, ['post.php', 'post-new.php', 'settings_page_messi-album'], true)) {
            return;
        }
        global $post;
        wp_enqueue_style('messi-album-admin', plugins_url('assets/admin.css', __FILE__), [], self::VERSION);
        wp_enqueue_script('messi-album-admin', plugins_url('assets/admin.js', __FILE__), ['jquery', 'jquery-ui-sortable'], self::VERSION, true);
        wp_localize_script('messi-album-admin', 'MessiAlbum', [
            'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('messi_album'),
            'postId' => $post ? $post->ID : 0,
        ]);
    }

    public function register_meta_box()
    {
        add_meta_box('messi-album-box', '图库相册', [$this, 'meta_box'], null, 'side');
    }

    public function button($editor_id)
    {
        if (current_user_can('edit_posts')) {
            echo '<button type="button" class="button ma-open" data-editor="'.esc_attr($editor_id).'">插入/编辑图库相册</button>';
        }
    }

    public function meta_box()
    {
        echo '<p>引用图库图片，不重复上传。</p><button type="button" class="button ma-open">管理图库相册</button>';
    }

    private function api($path, $query = [])
    {
        $options = get_option('messi_album_options', []);
        $base = isset($options['url']) ? $options['url'] : '';
        $token = defined('MESSI_ALBUM_TOKEN') ? MESSI_ALBUM_TOKEN : (isset($options['token']) ? $options['token'] : '');
        if (! $base || ! $token) {
            return new WP_Error('config', '请管理员先保存图库地址和只读令牌。');
        }
        $url = add_query_arg($query, $base.'/api/wordpress/v1/'.$path);
        $result = wp_safe_remote_get($url, [
            'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 2097152,
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token],
        ]);
        if (is_wp_error($result)) {
            return new WP_Error('network', '暂时无法连接图库，请稍后重试。');
        }
        $status = wp_remote_retrieve_response_code($result);
        if ($status !== 200) {
            $messages = [401 => '只读令牌无效或已撤销。', 403 => '图库拒绝了访问。', 404 => '相册不存在或已取消公开。', 410 => '分享链接已停用。', 422 => '请复制本站“整个相册”的分享链接。', 429 => '请求过于频繁，请稍后重试。'];

            return new WP_Error('http_'.$status, isset($messages[$status]) ? $messages[$status] : '图库暂时无法提供数据。');
        }
        $data = json_decode(wp_remote_retrieve_body($result), true);

        return is_array($data) ? $data : new WP_Error('response', '图库返回的数据无法读取。');
    }

    private function cache_key($album)
    {
        $options = get_option('messi_album_options', []);
        $token = defined('MESSI_ALBUM_TOKEN') ? MESSI_ALBUM_TOKEN : (isset($options['token']) ? $options['token'] : '');

        return 'ma_'.md5((isset($options['url']) ? $options['url'] : '').'|'.$token.'|'.$album);
    }

    private function album($id, $force = false)
    {
        $key = $this->cache_key($id);
        $cached = get_transient($key);
        if (! $force && is_array($cached) && time() - $cached['checked'] < 300) {
            return $cached;
        }
        if (! $force && get_transient($key.'_retry')) {
            return is_array($cached) && time() - $cached['checked'] < 900 ? $cached : new WP_Error('retry', '相册暂时不可用。');
        }
        $photos = [];
        $revision = null;
        for ($page = 1; $page <= 100; $page++) {
            $data = $this->api('albums/'.absint($id), ['page' => $page, 'per_page' => 100]);
            if (is_wp_error($data)) {
                // 明确的拒绝或下架立即清除，不把旧公开结果继续使用。
                if (in_array($data->get_error_code(), ['http_401', 'http_403', 'http_404', 'http_410'], true)) {
                    delete_transient($key);
                    $cached = false;
                }
                set_transient($key.'_retry', true, 60);
                if (! $force && is_array($cached) && time() - $cached['checked'] < 900) {
                    return $cached;
                }

                return $data;
            }
            if (empty($data['album']['id']) || (int) $data['album']['id'] !== (int) $id || ! isset($data['photos'], $data['meta']['last_page'], $data['meta']['total'], $data['revision'])
                || ! is_array($data['photos']) || ($revision !== null && $revision !== $data['revision'])) {
                return new WP_Error('changed', '相册数据不完整或正在更新，请重新读取。');
            }
            $revision = $data['revision'];
            foreach ($data['photos'] as $photo) {
                if (! isset($photo['id'], $photo['thumbnail_url'], $photo['display_url'])
                    || ! wp_http_validate_url($photo['thumbnail_url']) || ! wp_http_validate_url($photo['display_url'])) {
                    return new WP_Error('image', '相册包含无效的图片地址。');
                }
                $photos[$photo['id']] = $photo;
            }
            if ($page >= (int) $data['meta']['last_page']) {
                if (count($photos) !== (int) $data['meta']['total']) {
                    return new WP_Error('changed', '相册数量发生变化，请重新读取。');
                }
                $value = ['album' => $data['album'], 'photos' => $photos, 'checked' => time()];
                set_transient($key, $value, 900);
                delete_transient($key.'_retry');

                return $value;
            }
        }

        return new WP_Error('limit', '相册超过首版单册一万张上限，请拆分相册。');
    }

    public function ajax()
    {
        check_ajax_referer('messi_album', 'nonce');
        $op = isset($_POST['op']) ? sanitize_key($_POST['op']) : '';
        if ($op === 'health') {
            if (! current_user_can('manage_options')) {
                wp_send_json_error(['message' => '没有管理权限。'], 403);
            }
            $result = $this->api('health');
        } else {
            $post_id = isset($_POST['postId']) ? absint($_POST['postId']) : 0;
            if (! $post_id || ! current_user_can('edit_post', $post_id)) {
                wp_send_json_error(['message' => '没有编辑这篇文章的权限。'], 403);
            }
            $embeds = get_post_meta($post_id, self::META, true);
            $embeds = is_array($embeds) ? $embeds : [];
            if ($op === 'list') {
                $result = $embeds;
            } elseif ($op === 'load') {
                $ref = isset($_POST['ref']) ? sanitize_key($_POST['ref']) : '';
                if ($ref && isset($embeds[$ref])) {
                    $result = $this->album($embeds[$ref]['album_id'], true);
                } else {
                    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
                    $resolved = $this->api('resolve', ['url' => $url]);
                    $result = is_wp_error($resolved) ? $resolved : (isset($resolved['album']['id']) ? $this->album($resolved['album']['id'], true) : new WP_Error('response', '无法识别相册。'));
                }
            } elseif ($op === 'save') {
                $input = isset($_POST['value']) ? json_decode(wp_unslash($_POST['value']), true) : null;
                if (! is_array($input) || empty($input['album_id']) || empty($input['photos']) || ! is_array($input['photos']) || count($input['photos']) > 10000) {
                    wp_send_json_error(['message' => '请至少选择一张图片。'], 422);
                }
                $album = $this->album(absint($input['album_id']));
                if (is_wp_error($album)) {
                    $result = $album;
                } else {
                    $selected = [];
                    foreach ($input['photos'] as $item) {
                        if (is_array($item) && isset($item['id']) && is_string($item['id']) && isset($album['photos'][$item['id']])) {
                            $selected[$item['id']] = ['id' => $item['id'], 'caption' => sanitize_textarea_field(isset($item['caption']) ? $item['caption'] : '')];
                        }
                    }
                    if (! $selected) {
                        wp_send_json_error(['message' => '所选图片已不可用，请重新读取。'], 422);
                    }
                    $ref = isset($input['ref']) ? sanitize_key($input['ref']) : '';
                    if (! $ref || ! isset($embeds[$ref])) {
                        $ref = str_replace('-', '', wp_generate_uuid4());
                    }
                    $embeds[$ref] = ['album_id' => absint($input['album_id']), 'title' => sanitize_text_field($album['album']['title']),
                        'columns' => max(1, min(6, isset($input['columns']) ? absint($input['columns']) : 3)),
                        'captions' => ! empty($input['captions']), 'photos' => array_values($selected)];
                    update_post_meta($post_id, self::META, $embeds);
                    $result = ['ref' => $ref, 'shortcode' => '[messi_album ref="'.$ref.'"]'];
                }
            } else {
                $result = new WP_Error('operation', '未知操作。');
            }
        }
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }
        wp_send_json_success($result);
    }

    public function front_assets()
    {
        // 样式在页头加载，兼容主题在正文渲染短代码。
        wp_enqueue_style('messi-album', plugins_url('assets/front.css', __FILE__), [], self::VERSION);
        wp_register_script('messi-album', plugins_url('assets/front.js', __FILE__), [], self::VERSION, true);
    }

    public function render($atts)
    {
        $atts = shortcode_atts(['ref' => ''], $atts, 'messi_album');
        $embeds = get_post_meta(get_the_ID(), self::META, true);
        $ref = sanitize_key($atts['ref']);
        if (! is_array($embeds) || ! isset($embeds[$ref])) {
            return '<p class="ma-unavailable">相册引用不可用。</p>';
        }
        $embed = $embeds[$ref];
        $data = $this->album($embed['album_id']);
        if (is_wp_error($data)) {
            return '<p class="ma-unavailable">相册暂不可用，请稍后再试。</p>';
        }
        wp_enqueue_script('messi-album');
        $html = '<section class="ma-gallery" style="--ma-columns:'.(int) $embed['columns'].'" aria-label="'.esc_attr($data['album']['title']).'">';
        $count = 0;
        foreach ($embed['photos'] as $selected) {
            if (! isset($data['photos'][$selected['id']])) {
                continue;
            }
            $photo = $data['photos'][$selected['id']];
            $caption = $selected['caption'];
            $html .= '<figure class="ma-photo"><button type="button" class="ma-zoom" data-src="'.esc_url($photo['display_url']).'" data-caption="'.esc_attr($caption).'" aria-label="放大：'.esc_attr($photo['title']).'">';
            $html .= '<img loading="lazy" decoding="async" src="'.esc_url($photo['thumbnail_url']).'" alt="'.esc_attr($photo['alt']).'"></button>';
            if ($embed['captions'] && $caption !== '') {
                $html .= '<figcaption>'.esc_html($caption).'</figcaption>';
            }
            $html .= '</figure>';
            $count++;
        }
        if (! $count) {
            return '<p class="ma-unavailable">所选图片已不可用。</p>';
        }

        return $html.'<p class="ma-source"><a href="'.esc_url($data['album']['url']).'">查看完整相册</a></p></section>';
    }
}

new Messi_Album_Plugin;
