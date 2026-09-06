import { lazy, Suspense, useEffect, useMemo, useRef, useState } from "react";

const Globe = lazy(() => import("react-globe.gl"));
const locations = {
    DE: { lat: 52.52, lng: 13.405 },
    AT: { lat: 48.208, lng: 16.374 },
    TR: { lat: 41.008, lng: 28.978 },
};
const arcs = ["AT", "TR"].map((code) => ({
    startLat: locations.DE.lat,
    startLng: locations.DE.lng,
    endLat: locations[code].lat,
    endLng: locations[code].lng,
}));

export default function WorldGlobe({ countries, active }) {
    const hostRef = useRef(null);
    const globeRef = useRef(null);
    const [size, setSize] = useState(0);
    const [visible, setVisible] = useState(false);
    const [ready, setReady] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(false);
    const points = useMemo(
        () =>
            countries
                .filter((country) => locations[country.code])
                .map((country) => ({ ...country, ...locations[country.code] })),
        [countries],
    );

    useEffect(() => {
        const host = hostRef.current;
        const media = window.matchMedia("(prefers-reduced-motion: reduce)");
        const motion = () => setReducedMotion(media.matches);
        motion();
        media.addEventListener("change", motion);
        const resize = new ResizeObserver(() =>
            setSize(Math.round(host.clientWidth)),
        );
        resize.observe(host);
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { rootMargin: "220px" },
        );
        observer.observe(host);
        return () => {
            resize.disconnect();
            observer.disconnect();
            media.removeEventListener("change", motion);
        };
    }, []);

    useEffect(() => {
        if (!ready || !globeRef.current) return;
        const globe = globeRef.current;
        const controls = globe.controls();
        controls.enableZoom = false;
        controls.enablePan = false;
        controls.autoRotate = !reducedMotion;
        controls.autoRotateSpeed = 0.25;
        const location = locations[active] || locations.DE;
        globe.pointOfView(
            { lat: location.lat - 8, lng: location.lng, altitude: 1.85 },
            reducedMotion ? 0 : 900,
        );
    }, [ready, active, reducedMotion]);

    return (
        <div
            ref={hostRef}
            className={`group-globe group-globe--realistic ${ready ? "is-ready" : ""}`}
            role="img"
            aria-label={countries.map((country) => country.name).join(", ")}
        >
            <div className="group-globe__fallback" aria-hidden="true">
                <span />
                <span />
                <span />
            </div>
            {visible && size > 0 && (
                <Suspense fallback={null}>
                    <Globe
                        ref={globeRef}
                        width={size}
                        height={size}
                        globeImageUrl="/images/globe/earth-day.jpg"
                        backgroundColor="rgba(0,0,0,0)"
                        animateIn={false}
                        atmosphereColor="#8bbfff"
                        atmosphereAltitude={0.13}
                        pointsData={points}
                        pointLat="lat"
                        pointLng="lng"
                        pointLabel={() => ""}
                        pointColor={(point) =>
                            point.code === active ? "#ffe0a3" : "#ff963f"
                        }
                        pointRadius={(point) =>
                            point.code === active ? 1.65 : 1.15
                        }
                        pointAltitude={0.025}
                        pointsTransitionDuration={0}
                        arcsData={arcs}
                        arcColor={() => "#efb378"}
                        arcStroke={0.55}
                        arcAltitude={0.09}
                        arcsTransitionDuration={0}
                        onGlobeReady={() => setReady(true)}
                    />
                </Suspense>
            )}
        </div>
    );
}
