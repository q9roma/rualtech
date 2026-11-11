import { useCallback, type Dispatch, type SetStateAction } from 'react';

export function usePhotoWheel(count: number, onChange: Dispatch<SetStateAction<number>>) {
    const previewRef = useCallback((node: HTMLDivElement | null) => {
        if (!node || count < 2) return;
        let accumulated = 0;
        let lastEvent = 0;
        let lastSwitch = -Infinity;
        const onWheel = (event: WheelEvent) => {
            if (event.ctrlKey) return;
            event.preventDefault();
            const now = performance.now();
            const delta = Math.abs(event.deltaY) >= Math.abs(event.deltaX) ? event.deltaY : event.deltaX;
            if (!delta) return;
            if (now - lastEvent > 180 || Math.sign(delta) !== Math.sign(accumulated)) accumulated = 0;
            lastEvent = now;
            if (now - lastSwitch < 250) return;
            accumulated += delta * (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? node.clientHeight : 1);
            if (Math.abs(accumulated) < 40) return;
            const direction = Math.sign(accumulated);
            onChange(previous => (previous + direction + count) % count);
            accumulated = 0;
            lastSwitch = now;
        };
        node.addEventListener('wheel', onWheel, { passive: false });
        return () => node.removeEventListener('wheel', onWheel);
    }, [count, onChange]);
    return previewRef;
}
