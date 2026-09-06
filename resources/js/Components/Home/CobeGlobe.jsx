import { useEffect, useRef, useState } from 'react';

const locations = {
    DE: { location: [52.52, 13.405], size: 0.075, phi: 0.18 },
    AT: { location: [48.208, 16.374], size: 0.07, phi: 0.12 },
    TR: { location: [41.008, 28.978], size: 0.085, phi: -0.08 },
};

export default function WorldGlobe({ countries, active }) {
    const hostRef = useRef(null);
    const canvasRef = useRef(null);
    const targetPhi = useRef(locations[active]?.phi ?? 0.18);
    const [ready, setReady] = useState(false);

    useEffect(() => { targetPhi.current = locations[active]?.phi ?? targetPhi.current; }, [active]);

    useEffect(() => {
        const host = hostRef.current;
        const canvas = canvasRef.current;
        if (!host || !canvas) return undefined;
        let globe;
        let resizeObserver;
        let animationFrame;
        let disposed = false;
        let phi = targetPhi.current;
        let pointer = null;
        let drag = 0;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const observer = new IntersectionObserver(async ([entry]) => {
            if (!entry.isIntersecting || globe) return;
            observer.disconnect();
            try {
                const { default: createGlobe } = await import('cobe');
                if (disposed) return;
                const dimensions = () => {
                    const size = Math.max(280, Math.min(host.clientWidth, 650));
                    return { width: size * 2, height: size * 2 };
                };
                const create = () => {
                    globe = createGlobe(canvas, {
                        devicePixelRatio: Math.min(window.devicePixelRatio, 2),
                        ...dimensions(),
                        phi,
                        theta: 0.18,
                        dark: 0,
                        diffuse: 1.35,
                        mapSamples: 16000,
                        mapBrightness: 7,
                        mapBaseBrightness: 0.03,
                        baseColor: [0.18, 0.52, 0.5],
                        markerColor: [0.95, 0.52, 0.28],
                        glowColor: [0.24, 0.7, 0.66],
                        markers: countries.map(country => locations[country.code]).filter(Boolean),
                        arcs: [
                            { from: locations.DE.location, to: locations.AT.location },
                            { from: locations.DE.location, to: locations.TR.location },
                        ],
                        arcColor: [0.96, 0.56, 0.3],
                        arcWidth: 0.75,
                        arcHeight: 0.18,
                    });
                };
                create();
                // COBE 2 renders through update(); it no longer calls onRender.
                // Keep drawing after the asynchronously loaded map texture is ready.
                const render = () => {
                    if (disposed) return;
                    const desired = targetPhi.current + drag;
                    phi += (desired - phi) * 0.035;
                    if (!reduceMotion && pointer === null) phi += 0.0012;
                    globe.update({ phi });
                    setReady(true);
                    animationFrame = requestAnimationFrame(render);
                };
                animationFrame = requestAnimationFrame(render);
                resizeObserver = new ResizeObserver(() => globe.update(dimensions()));
                resizeObserver.observe(host);
            } catch {
                setReady(false);
            }
        }, { rootMargin: '220px' });
        observer.observe(host);

        const down = event => { pointer = event.clientX; canvas.setPointerCapture(event.pointerId); };
        const move = event => { if (pointer !== null) { drag += (event.clientX - pointer) / 170; pointer = event.clientX; } };
        const up = () => { pointer = null; };
        canvas.addEventListener('pointerdown', down);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', up);
        canvas.addEventListener('pointercancel', up);
        return () => {
            disposed = true;
            cancelAnimationFrame(animationFrame);
            observer.disconnect();
            resizeObserver?.disconnect();
            globe?.destroy();
            canvas.removeEventListener('pointerdown', down);
            canvas.removeEventListener('pointermove', move);
            canvas.removeEventListener('pointerup', up);
            canvas.removeEventListener('pointercancel', up);
        };
    }, [countries]);

    return <div ref={hostRef} className={`group-globe ${ready ? 'is-ready' : ''}`}>
        <div className="group-globe__fallback" aria-hidden="true"><span /><span /><span /></div>
        <canvas ref={canvasRef} aria-label={countries.map(country => country.name).join(', ')} />
    </div>;
}
