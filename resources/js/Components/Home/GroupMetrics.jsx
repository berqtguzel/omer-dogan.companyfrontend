import { useEffect, useRef, useState } from 'react';
import Container from '@/Components/Common/Container';
import { useTranslation } from 'react-i18next';

function AnimatedValue({ value }) {
    const target = Number.parseFloat(String(value).replace(',', '.'));
    const [display, setDisplay] = useState(Number.isFinite(target) ? 0 : value);
    const ref = useRef(null);

    useEffect(() => {
        if (!Number.isFinite(target) || !ref.current) return undefined;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setDisplay(target);
            return undefined;
        }

        let frame;
        const observer = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) return;
            const started = performance.now();
            const tick = (now) => {
                const progress = Math.min((now - started) / 1200, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                setDisplay(Math.round(target * eased));
                if (progress < 1) frame = requestAnimationFrame(tick);
            };
            frame = requestAnimationFrame(tick);
            observer.disconnect();
        }, { threshold: 0.45 });
        observer.observe(ref.current);
        return () => { observer.disconnect(); cancelAnimationFrame(frame); };
    }, [target]);

    return <span ref={ref}>{display}</span>;
}

export default function GroupMetrics({ items }) {
    const { t } = useTranslation();
    if (!items.length) return null;
    return <section className="group-metrics" aria-label={t('corporateHome.metricsLabel')}>
        <Container><dl>{items.map((item, index) => <div key={`${item.label}-${index}`}>
            <dt>{item.label}</dt><dd><AnimatedValue value={item.value} /></dd>
        </div>)}</dl></Container>
    </section>;
}
