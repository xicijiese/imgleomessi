<div x-data="{ failed: false }" wire:key="setting-preview-{{ hash('sha256', $url) }}">
    <img src="{{ $url }}" alt="当前图片预览" loading="lazy"
         style="max-height: 160px; max-width: 100%; object-fit: contain"
         x-show="!failed" x-on:error="failed = true">
    <p x-cloak x-show="failed" role="status">预览加载失败，仍可移除或重新选择图片。</p>
</div>
