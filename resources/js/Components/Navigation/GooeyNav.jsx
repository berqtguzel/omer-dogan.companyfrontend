import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import './GooeyNav.css';

// Adapted from the React Bits GooeyNav source supplied for this project.
// Keep the existing semantic navigation tree and its native link behavior.
export default function GooeyNav({ children, particleCount = 10, animationTime = 600 }) {
    const rootRef = useRef(null);
    const effectRef = useRef(null);
    const { url } = usePage();

    useEffect(() => {
        const root = rootRef.current;
        const effect = effectRef.current;
        let selected;
        let timer;
        const targets = () => [...root.querySelectorAll(':scope > ul > li > a, :scope > ul > li > details > summary')];
        const clear = () => { clearTimeout(timer); effect.querySelectorAll('.gooey-nav__particle').forEach(node => node.remove()); };
        const place = (target, animate = false) => {
            if (!target) { effect.hidden = true; selected?.removeAttribute('data-gooey'); selected = null; return; }
            if (animate && target === selected) return;
            clear();
            selected?.removeAttribute('data-gooey');
            selected = target;
            selected.dataset.gooey = 'true';
            const box = target.getBoundingClientRect();
            const host = root.getBoundingClientRect();
            Object.assign(effect.style, { left: `${box.left - host.left}px`, top: `${box.top - host.top + 5}px`, width: `${box.width}px`, height: `${Math.max(0, box.height - 10)}px` });
            effect.hidden = false;
            if (!animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            for (let index = 0; index < particleCount; index++) {
                const particle = document.createElement('span');
                particle.className = 'gooey-nav__particle';
                const angle = index / particleCount * Math.PI * 2;
                particle.style.setProperty('--x', `${Math.cos(angle) * (box.width / 2 + 12)}px`);
                particle.style.setProperty('--y', `${Math.sin(angle) * 26}px`);
                particle.style.setProperty('--duration', `${animationTime + Math.random() * 200}ms`);
                effect.appendChild(particle);
            }
            timer = setTimeout(clear, animationTime + 250);
        };
        const restore = () => place(targets().find(target => target.matches('[aria-current="page"], [data-active]')));
        const interact = event => {
            const target = targets().find(node => node === event.target || node.contains(event.target));
            if (target) place(target, true);
        };
        const leave = () => { if (!root.contains(document.activeElement)) restore(); };
        const blur = event => { if (!root.contains(event.relatedTarget)) restore(); };
        root.addEventListener('pointerover', interact);
        root.addEventListener('focusin', interact);
        root.addEventListener('pointerleave', leave);
        root.addEventListener('focusout', blur);
        const observer = new ResizeObserver(() => selected ? place(selected) : restore());
        observer.observe(root);
        restore();
        return () => {
            clear(); observer.disconnect(); selected?.removeAttribute('data-gooey');
            root.removeEventListener('pointerover', interact);
            root.removeEventListener('focusin', interact);
            root.removeEventListener('pointerleave', leave);
            root.removeEventListener('focusout', blur);
        };
    }, [url, children, particleCount, animationTime]);

    return <div className="gooey-nav" ref={rootRef}>
        <span className="gooey-nav__effect" aria-hidden="true" ref={effectRef} hidden />
        {children}
    </div>;
}
