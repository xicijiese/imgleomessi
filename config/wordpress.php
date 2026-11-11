<?php

return [
    // 仅保存令牌的 SHA-256，不将明文令牌写入项目。
    'token_hash' => env('WORDPRESS_ALBUM_TOKEN_HASH', ''),
];
