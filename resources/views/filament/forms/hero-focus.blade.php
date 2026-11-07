<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div wire:key="hero-focus-{{ md5($getStatePath().($imageUrl ?? '')) }}"
         x-data="{ focus: $wire.$entangle('{{ $getStatePath() }}'), failed: false }"
         x-init="if (!focus || typeof focus !== 'object') focus = { x: 50, y: 50 }"
         style="display: grid; gap: 12px;">
        @if ($imageUrl)
            <div style="overflow: hidden; border-radius: 8px; background: #98cef0; {{ $device === 'mobile' ? 'width: min(100%, 180px); aspect-ratio: 390 / 738;' : 'width: 100%; aspect-ratio: 1440 / 774;' }}">
                <img src="{{ $imageUrl }}" alt="{{ $getLabel() }}裁切预览"
                     x-show="!failed" x-on:error="failed = true"
                     x-bind:style="{ objectPosition: `${focus.x}% ${focus.y}%` }"
                     style="display: block; width: 100%; height: 100%; object-fit: cover;" />
                <p x-show="failed" x-cloak style="padding: 16px; font-size: 14px;">图片暂不可用，请检查上传结果。</p>
            </div>
        @else
            <p style="font-size: 14px;">上传头图后可预览；未上传本端图片时，沿用另一端图片。</p>
        @endif
        <p style="font-size: 12px; opacity: .7;">预览比例仅供参考，实际裁切随屏幕变化；不会改变头图尺寸。</p>
        <label style="display: grid; gap: 8px; font-size: 14px;">
            <span>横向位置：<span x-text="focus.x"></span>%（左 → 右）</span>
            <input type="range" min="0" max="100" step="1" x-model.number="focus.x"
                   aria-label="{{ $getLabel() }}横向位置" style="display: block; width: 100%;" />
        </label>
        <label style="display: grid; gap: 8px; font-size: 14px;">
            <span>纵向位置：<span x-text="focus.y"></span>%（上 → 下）</span>
            <input type="range" min="0" max="100" step="1" x-model.number="focus.y"
                   aria-label="{{ $getLabel() }}纵向位置" style="display: block; width: 100%;" />
        </label>
        <x-filament::button type="button" color="gray" size="sm" x-on:click="focus = { x: 50, y: 50 }">
            恢复居中
        </x-filament::button>
        <p style="font-size: 12px; opacity: .7;">调整后需点击页面的“保存设置”才会应用到首页。</p>
    </div>
</x-dynamic-component>
