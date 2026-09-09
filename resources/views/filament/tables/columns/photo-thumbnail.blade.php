<div
    x-data="{ previewOpen: false }"
    x-on:mouseenter="previewOpen = true"
    x-on:mouseleave="previewOpen = false"
    style="position: relative; width: 4rem; height: 4rem; overflow: visible;"
>
    @if (filled($thumbnailUrl))
        <img
            src="{{ $thumbnailUrl }}"
            alt="{{ $alt }}"
            loading="lazy"
            style="display: block; width: 4rem; height: 4rem; border-radius: 0.375rem; object-fit: cover; box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);"
        >
        @if (filled($previewUrl))
            <div
                x-cloak
                x-show="previewOpen"
                style="position: absolute; left: 50%; bottom: calc(100% + 0.5rem); z-index: 50; display: none; transform: translateX(-50%); padding: 0.375rem; border-radius: 0.5rem; background: white; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.2), 0 8px 10px -6px rgb(0 0 0 / 0.2);"
            >
                <img
                    src="{{ $previewUrl }}"
                    alt="{{ $alt }}"
                    loading="lazy"
                    style="display: block; width: auto; height: auto; max-width: 22rem; max-height: 20rem; border-radius: 0.375rem; object-fit: contain;"
                >
            </div>
        @endif
    @else
        <div style="display: flex; width: 4rem; height: 4rem; align-items: center; justify-content: center; border-radius: 0.375rem; background: #f3f4f6; color: #6b7280; font-size: 0.75rem; line-height: 1rem; box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);">
            未生成
        </div>
    @endif
</div>