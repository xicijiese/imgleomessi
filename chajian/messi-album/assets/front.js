(function () {
    'use strict';
    var dialog, image, caption, counter, prev, next, items = [], index = 0, opener, startX = null;
    function show(position) {
        index = position;
        image.src = items[index].dataset.src;
        image.alt = items[index].querySelector('img').alt;
        caption.textContent = items[index].dataset.caption || '';
        counter.textContent = (index + 1) + ' / ' + items.length;
        prev.disabled = index === 0; next.disabled = index === items.length - 1;
    }
    function setup() {
        dialog = document.createElement('dialog'); dialog.className = 'ma-lightbox';
        dialog.setAttribute('aria-label', '相册图片浏览');
        dialog.innerHTML = '<div class="ma-controls"><button type="button" data-action="prev">上一张</button><span aria-live="polite"></span><button type="button" data-action="next">下一张</button><button type="button" data-action="close">关闭</button></div><img alt=""><p></p>';
        document.body.appendChild(dialog);
        image = dialog.querySelector('img'); caption = dialog.querySelector('p'); counter = dialog.querySelector('span');
        prev = dialog.querySelector('[data-action="prev"]'); next = dialog.querySelector('[data-action="next"]');
        dialog.addEventListener('click', function (event) {
            var action = event.target.dataset.action;
            if (action === 'close') { dialog.close(); }
            if (action === 'prev' && index > 0) { show(index - 1); }
            if (action === 'next' && index < items.length - 1) { show(index + 1); }
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); show(index - 1); }
            if (event.key === 'ArrowRight' && index < items.length - 1) { event.preventDefault(); show(index + 1); }
        });
        image.addEventListener('touchstart', function (event) { startX = event.touches[0].clientX; }, {passive: true});
        image.addEventListener('touchend', function (event) {
            if (startX === null) { return; }
            var delta = event.changedTouches[0].clientX - startX; startX = null;
            if (delta > 60 && index > 0) { show(index - 1); }
            if (delta < -60 && index < items.length - 1) { show(index + 1); }
        }, {passive: true});
        dialog.addEventListener('close', function () { image.removeAttribute('src'); if (opener) { opener.focus(); } });
        image.addEventListener('error', function () { caption.textContent = '图片暂时无法加载，请稍后重试。'; });
    }
    // 图片不包在链接内，减少主题或第三方灯箱自动接管的机会。
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.ma-zoom');
        if (!button) { return; }
        event.preventDefault(); event.stopImmediatePropagation();
        if (!dialog) { setup(); }
        opener = button;
        items = Array.prototype.slice.call(button.closest('.ma-gallery').querySelectorAll('.ma-zoom'));
        show(items.indexOf(button)); dialog.showModal();
    }, true);
})();
