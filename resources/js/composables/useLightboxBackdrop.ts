export function useLightboxBackdrop(close: () => void) {
    let start: { x: number; y: number; backdrop: boolean } | null = null;
    return {
        pointerDown(event: PointerEvent) {
            start = {
                x: event.clientX,
                y: event.clientY,
                backdrop: event.target === event.currentTarget,
            };
        },
        click(event: MouseEvent) {
            if (
                start?.backdrop &&
                event.target === event.currentTarget &&
                Math.hypot(event.clientX - start.x, event.clientY - start.y) < 6
            )
                close();
            start = null;
        },
    };
}
