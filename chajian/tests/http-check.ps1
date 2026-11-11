param([int]$Port = 9413)
$ErrorActionPreference = 'Stop'
if ($Port -notin @(9413,9414)) { throw '仅允许访问隔离测试站端口。' }
$base = "http://127.0.0.1:$Port"
$report = Invoke-RestMethod "$base/wp-content/ma-tests/report.php"
$null = Invoke-WebRequest "$base/wp-login.php" -SessionVariable maSession
$null = Invoke-WebRequest "$base/wp-login.php" -WebSession $maSession -Method Post -Body @{log='admin';pwd='password';redirect_to="$base/wp-admin/";testcookie='1'}
$page = (Invoke-WebRequest "$base/wp-admin/post.php?post=$($report.post_id)&action=edit" -WebSession $maSession).Content
$match = [regex]::Match($page, 'var MessiAlbum = (\{[^;]+\});')
if (!$match.Success) { throw '无法获取插件编辑入口。' }
$config = $match.Groups[1].Value | ConvertFrom-Json
$script:checks = 0
function Expect($condition, $message) {
    if (!$condition) { throw $message }
    $script:checks++
}
function Call-Album($operation, $extra = @{}) {
    $body = @{action='messi_album';op=$operation;postId=$report.post_id;nonce=$config.nonce}
    foreach ($key in $extra.Keys) { $body[$key] = $extra[$key] }
    $response = Invoke-WebRequest "$base/wp-admin/admin-ajax.php" -WebSession $maSession -Method Post -Body $body -SkipHttpErrorCheck
    return $response.Content | ConvertFrom-Json
}
Expect (Call-Album 'health').success '连接测试失败'
Expect ((Call-Album 'list').data.testref.album_id -eq 77) '无法重新编辑已有引用'
Expect (((Call-Album 'load' @{ref='testref'}).data.photos.PSObject.Properties | Measure-Object).Count -eq 2) '多页读取不完整'
$value = @{ref='testref';album_id=77;columns=2;captions=$true;photos=@(@{id='photo-2';caption='文章独立说明'},@{id='foreign';caption='不应保存'})} | ConvertTo-Json -Depth 5 -Compress
Expect (Call-Album 'save' @{value=$value}).success '保存失败'
$stored = (Call-Album 'list').data.testref
Expect ($stored.photos.Count -eq 1 -and $stored.photos[0].id -eq 'photo-2') '没有排除外部图片'
Expect ($stored.columns -eq 2 -and $stored.photos[0].caption -eq '文章独立说明') '说明或列数保存失败'
$newValue = @{album_id=77;columns=3;captions=$true;photos=@(@{id='photo-1';caption='新引用'})} | ConvertTo-Json -Depth 5 -Compress
$created = Call-Album 'save' @{value=$newValue}
Expect ($created.success -and $created.data.ref -match '^[a-f0-9]{32}$') '无法新建稳定引用'
Expect ($created.data.shortcode -eq ('[messi_album ref="' + $created.data.ref + '"]')) '短代码不正确'
$invalid = Invoke-WebRequest "$base/wp-admin/admin-ajax.php" -WebSession $maSession -Method Post -Body @{action='messi_album';op='list';postId=$report.post_id;nonce='invalid'} -SkipHttpErrorCheck
Expect ($invalid.StatusCode -eq 403) '未拒绝无效凭证'
$guest = Invoke-WebRequest "$base/wp-admin/admin-ajax.php" -Method Post -Body @{action='messi_album';op='list';postId=$report.post_id;nonce=$config.nonce} -SkipHttpErrorCheck
Expect ($guest.StatusCode -ne 200) '未拒绝游客操作'
@{wordpress=$report.wordpress;php=$report.php;http_checks=$script:checks} | ConvertTo-Json -Compress
