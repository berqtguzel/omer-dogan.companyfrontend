import { useEffect, useRef } from 'react';

// CSS-only ambient motion: no canvas, pointer tracking or animation-frame loop.
export default function HomeAmbientBackground({ children }) {
    const hostRef = useRef(null);

    useEffect(() => {
        const host = hostRef.current;
        let inView = false;
        const update = () => {
            host.dataset.playing = String(inView && !document.hidden);
        };
        const observer = new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting;
            update();
        });
        observer.observe(host);
        document.addEventListener('visibilitychange', update);
        return () => {
            observer.disconnect();
            document.removeEventListener('visibilitychange', update);
        };
    }, []);

    return <div className="home-ambient" ref={hostRef}>
        <div className="home-ambient__art" aria-hidden="true">
            <span className="home-ambient__wash" />
            <span className="home-ambient__grid" />
        </div>
        <div className="home-ambient__content">{children}</div>
    </div>;
}
