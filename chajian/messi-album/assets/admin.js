(function ($) {
    'use strict';
    var config = window.MessiAlbum;
    var dialog, list, status, current = null, embeds = {}, ref = '', editor = 'content';
    var busy = false;

    function request(op, values) {
        return $.ajax({url: config.ajax, method: 'POST', dataType: 'json', data: $.extend({
            action: 'messi_album', nonce: config.nonce, postId: config.postId, op: op
        }, values || {})}).then(function (response) {
            if (!response.success) { throw new Error(response.data.message); }
            return response.data;
        }, function (xhr) {
            throw new Error(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '请求失败，请检查连接后重试。');
        });
    }

    function working(value, message) {
        busy = value;
        dialog.find('button, input, select, textarea').prop('disabled', value);
        dialog.attr('aria-busy', value ? 'true' : 'false');
        status.text(message || '');
    }

    function build() {
        if (dialog) { return; }
        dialog = $('<dialog class="ma-editor" aria-labelledby="ma-heading">');
        dialog.html('<form method="dialog" class="ma-editor-head"><h2 id="ma-heading">插入/编辑图库相册</h2><button class="button" value="close">关闭</button></form>' +
            '<label>已有引用 <select id="ma-existing"><option value="">新建相册引用</option></select></label>' +
            '<div class="ma-link"><label for="ma-link">整个相册的分享链接</label><input type="url" id="ma-link" placeholder="https://img.leomessi.cn/s/…"><button type="button" class="button" id="ma-load">读取/检查更新</button></div>' +
            '<p class="ma-status" role="status" aria-live="polite"></p>' +
            '<div class="ma-tools"><button type="button" class="button" id="ma-all">全选</button> <button type="button" class="button" id="ma-none">取消全选</button> ' +
            '<label>桌面列数 <select id="ma-columns"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option><option>6</option></select></label> ' +
            '<label><input type="checkbox" id="ma-captions" checked> 显示图片说明</label></div>' +
            '<p>拖动图片调整顺序，也可用前移/后移按钮。新增图片默认不选中，文章原有顺序和说明会保留。</p>' +
            '<ul class="ma-items"></ul><div class="ma-editor-foot"><button type="button" class="button button-primary" id="ma-insert">保存并生成短代码</button>' +
            '<label for="ma-shortcode">短代码</label><input id="ma-shortcode" type="text" readonly placeholder="保存后生成，可复制到短代码区块"></div>');
        $('body').append(dialog);
        list = dialog.find('.ma-items'); status = dialog.find('.ma-status');
        list.sortable({items: '> li', handle: '.ma-drag', tolerance: 'pointer'});
        dialog.on('click', '#ma-load', load);
        dialog.on('click', '#ma-all', function () { list.find('.ma-check').prop('checked', true); });
        dialog.on('click', '#ma-none', function () { list.find('.ma-check').prop('checked', false); });
        dialog.on('click', '.ma-before, .ma-after', function () {
            var item = $(this).closest('li');
            if ($(this).hasClass('ma-before')) { item.prev().before(item); }
            else { item.next().after(item); }
        });
        dialog.on('change', '#ma-existing', function () {
            ref = $(this).val(); current = null; list.empty();
            dialog.find('#ma-link').val(''); dialog.find('#ma-shortcode').val('');
            status.text(ref ? '点击“读取/检查更新”加载这份引用。' : '粘贴相册分享链接后读取。');
        });
        dialog.on('click', '#ma-insert', save);
        dialog.on('cancel', function (event) { if (busy) { event.preventDefault(); } });
    }

    function load() {
        working(true, '正在读取相册，较大的相册需要稍等…');
        request('load', {url: dialog.find('#ma-link').val(), ref: ref}).then(function (data) {
            current = data;
            list.empty();
            var saved = ref && embeds[ref] ? embeds[ref] : null;
            var order = saved ? saved.photos.map(function (p) { return p.id; }) : [];
            var captions = {};
            if (saved) {
                saved.photos.forEach(function (p) { captions[p.id] = p.caption; });
                dialog.find('#ma-columns').val(saved.columns);
                dialog.find('#ma-captions').prop('checked', !!saved.captions);
            }
            var ids = order.filter(function (id) { return !!data.photos[id]; });
            var removed = order.length - ids.length;
            Object.keys(data.photos).forEach(function (id) { if (ids.indexOf(id) < 0) { ids.push(id); } });
            ids.forEach(function (id) {
                var p = data.photos[id];
                var item = $('<li>').attr('data-photo', id);
                var label = $('<label class="ma-select">');
                label.append($('<input class="ma-check" type="checkbox">').prop('checked', !saved || order.indexOf(id) >= 0));
                label.append($('<img loading="lazy">').attr({src: p.thumbnail_url, alt: p.alt}));
                label.append($('<span>').text(p.title));
                item.append(label);
                item.append($('<label>').text('本篇图片说明').append($('<textarea rows="2">').val(Object.prototype.hasOwnProperty.call(captions, id) ? captions[id] : p.caption)));
                item.append('<div class="ma-order"><span class="ma-drag" title="拖动排序">拖动排序</span> <button type="button" class="button ma-before" aria-label="图片前移">前移</button> <button type="button" class="button ma-after" aria-label="图片后移">后移</button></div>');
                list.append(item);
            });
            working(false, data.album.title + '：当前可用 ' + ids.length + ' 张' + (saved ? '，原选图不可用 ' + removed + ' 张；未选择的图片可手动勾选。' : '。'));
        }).catch(function (error) { working(false, error.message); });
    }

    function save() {
        if (!current) { status.text('请先读取相册。'); return; }
        var photos = [];
        list.children().each(function () {
            var item = $(this);
            if (item.find('.ma-check').prop('checked')) { photos.push({id: item.attr('data-photo'), caption: item.find('textarea').val()}); }
        });
        if (!photos.length) { status.text('请至少选择一张图片。'); return; }
        var editing = !!ref;
        var value = {ref: ref, album_id: current.album.id, columns: Number(dialog.find('#ma-columns').val()), captions: dialog.find('#ma-captions').prop('checked'), photos: photos};
        working(true, '正在保存相册引用…');
        request('save', {value: JSON.stringify(value)}).then(function (result) {
            ref = result.ref;
            embeds[ref] = value;
            if (!editing) {
                dialog.find('#ma-existing').append($('<option>').val(ref).text(current.album.title + ' · ' + ref.slice(0, 6)));
                dialog.find('#ma-existing').val(ref);
                var classic = document.getElementById(editor);
                if (classic && window.wp && wp.media && wp.media.editor) {
                    window.wpActiveEditor = editor;
                    wp.media.editor.insert(result.shortcode);
                }
            }
            dialog.find('#ma-shortcode').val(result.shortcode);
            working(false, editing ? '引用已更新。文章中的原短代码无需替换；预览页面查看效果。' : '引用已保存。经典编辑器已尝试插入；区块编辑器请复制下方短代码到“短代码”区块。请保存文章。');
        }).catch(function (error) { working(false, error.message); });
    }

    $(document).on('click', '.ma-open', function () {
        editor = $(this).attr('data-editor') || 'content'; build();
        if (dialog[0].showModal) { dialog[0].showModal(); }
        else { dialog.attr('open', ''); }
        working(true, '正在读取本篇相册引用…');
        request('list').then(function (data) {
            embeds = data; ref = ''; current = null; list.empty();
            var select = dialog.find('#ma-existing').empty().append('<option value="">新建相册引用</option>');
            Object.keys(data).forEach(function (key) { select.append($('<option>').val(key).text(data[key].title + ' · ' + key.slice(0, 6))); });
            dialog.find('#ma-link, #ma-shortcode').val('');
            working(false, '粘贴分享链接新建，或选择已有引用后读取。');
        }).catch(function (error) { working(false, error.message); });
    });
    $(document).on('click', '#ma-test', function () {
        var button = $(this).prop('disabled', true), output = $('#ma-test-result').text('正在连接…');
        request('health').then(function () { output.text('连接成功，可以读取公开相册。'); })
            .catch(function (error) { output.text(error.message); }).then(function () { button.prop('disabled', false); });
    });
})(jQuery);
