// 在组件自身错误处理前回退，避免 AVIF 不支持或样式失败时组件提前隐藏图片。
document.addEventListener('error', (event) => {
    const image = event.target;
    if (!(image instanceof HTMLImageElement)) return;
    const source = image.getAttribute('src') || '';
    const fallback = source.replace(/(\.webp)\/(gallery_thumb_avif|gallery_display_avif)(?=\?|$)/i, '$1');
    if (source === fallback || !/^https?:\/\//.test(source)) return;
    event.stopImmediatePropagation();
    image.src = fallback;
}, true);

// SSR 图片可能在脚本加载前就失败，补查已经完成但未解码的图片。
function retryExistingImages() {
    document.querySelectorAll('img').forEach((image) => {
        if (image.complete && image.naturalWidth === 0) image.dispatchEvent(new Event('error'));
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', retryExistingImages, { once: true });
else retryExistingImages();
