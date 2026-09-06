import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SiteLink from '@/Components/Navigation/SiteLink';
import { buildResponsiveImage } from '@/utils/imageOptimizer';

export default function CorporateHero({ hero, areas, contactLink }) {
    const { t } = useTranslation();
    const [videoFailed, setVideoFailed] = useState(false);
    const video = hero.video || '/videos/SliderVideo.mp4';
    const poster = buildResponsiveImage(hero.image, { widths: [800, 1200, 1600], width: 1600, quality: 76 });
    const title = hero.title || t('corporatePages.page');
    useEffect(() => setVideoFailed(false), [video]);

    return <section className="group-hero group-hero--cinematic" aria-labelledby="group-hero-title">
        {poster.src && <Head><link head-key="corporate-hero-preload" rel="preload" as="image" href={poster.src}
            imageSrcSet={poster.srcSet || undefined} imageSizes="100vw" fetchpriority="high" /></Head>}
        <div className="group-hero__backdrop" aria-hidden="true">
            {!videoFailed && <video src={video} poster={hero.image || undefined} autoPlay muted loop playsInline preload="metadata" onError={() => setVideoFailed(true)} />}
            {videoFailed && hero.image && <img src={poster.src || hero.image} alt="" />}
            <span className="group-hero__veil" />
            <span className="group-hero__glow group-hero__glow--one" />
            <span className="group-hero__glow group-hero__glow--two" />
        </div>
        <Container>
            <div className="group-hero__content">
                <p className="group-hero__kicker"><span />{t('corporateHome.areasEyebrow')}</p>
                <h1 id="group-hero-title" className="corporate-heading">{title}</h1>
                {hero.description && <p className="group-hero__description">{hero.description}</p>}
                <div className="group-actions">
                    <SiteLink item={hero.primary} className="corporate-button corporate-button--light" data-track-key="home_group_discover" data-track-location="hero" data-track-action="navigate">
                        {hero.primaryLabel || t('corporateHome.discover')}<span aria-hidden="true">↗</span>
                    </SiteLink>
                    <SiteLink item={contactLink} className="group-text-link group-text-link--light">{t('corporateHome.contact')}<span aria-hidden="true">↗</span></SiteLink>
                </div>
            </div>
            {areas.length > 0 && <nav className="group-hero__directory" aria-label={t('corporateHome.areasEyebrow')}>
                <ol>{areas.slice(0, 4).map((area, index) => <li key={area.id}>
                    <a href={`#area-${area.id}`}><span className="group-number">0{index + 1}</span><span>{area.title || t(`corporateHome.areas.${area.id}.title`)}</span><span aria-hidden="true">↗</span></a>
                </li>)}</ol>
            </nav>}
            <a className="group-hero__scroll" href="#main-dialog" aria-label={t('corporateHome.discover')}><i aria-hidden="true" /></a>
        </Container>
    </section>;
}
