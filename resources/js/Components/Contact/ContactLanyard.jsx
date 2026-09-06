import { Component, lazy, Suspense, useEffect, useRef, useState } from 'react';
import './ContactLanyard.css';
const Lanyard = lazy(() => import('./Lanyard/Lanyard'));
class SceneBoundary extends Component {
    state = { failed: false };
    static getDerivedStateFromError() { return { failed: true }; }
    render() { return this.state.failed ? this.props.fallback : this.props.children; }
}
export default function ContactLanyard() {
    const host = useRef(null);
    const [show, setShow] = useState(false);
    useEffect(() => {
        const header = document.querySelector('.corporate-header');
        const position = () => {
            if (header) host.current.style.setProperty('--lanyard-top', `${header.getBoundingClientRect().bottom - 65}px`);
        };
        const resize = new ResizeObserver(position);
        if (header) resize.observe(header);
        position();
        window.addEventListener('scroll', position, { passive: true });
        return () => { resize.disconnect(); window.removeEventListener('scroll', position); };
    }, []);
    useEffect(() => {
        let visible = false;
        const media = window.matchMedia('(min-width: 768px) and (prefers-reduced-motion: no-preference)');
        const update = () => setShow(visible && media.matches && !document.hidden);
        const observer = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; update(); });
        observer.observe(host.current);
        media.addEventListener('change', update);
        document.addEventListener('visibilitychange', update);
        return () => { observer.disconnect(); media.removeEventListener('change', update); document.removeEventListener('visibilitychange', update); };
    }, []);
    const card = <div className="contact-lanyard__static"><span className="contact-lanyard__strap" /><img src="/images/logo/logo.png" alt="" width="240" height="180" /></div>;
    return <div ref={host} className="contact-lanyard">
            <div className="contact-lanyard__visual" aria-hidden="true">
                {show ? <SceneBoundary fallback={card}><Suspense fallback={card}>
                    <Lanyard position={[0, 0, 22]} frontImage="/images/logo/logo.png" backImage="/images/logo/logo.png" imageFit="contain" />
                </Suspense></SceneBoundary> : card}
            </div>
    </div>;
}
