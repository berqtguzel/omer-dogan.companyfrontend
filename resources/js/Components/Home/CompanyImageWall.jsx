import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import DriftWall from './DriftWall';
import SiteLink from '@/Components/Navigation/SiteLink';

const images = [
    ['imgi_8_werrapark-heubacherhohe.png', 'Werrapark Heubacherhöhe'],
    ['imgi_10_werrapark-sportcenter.png', 'Werrapark Sportcenter'],
    ['imgi_13_hamburgoi.png', 'Hamburg'],
    ['imgi_14_hannoveroi.png', 'Hannover'],
    ['imgi_15_dhs-scaled.png', 'DHS'],
    ['imgi_17_dhi.png', 'DHI'],
].map(([file, title]) => ({ image: `/images/sirket-resimleri/${file}`, title }));

export default function CompanyImageWall({ companies = [] }) {
    const apiItems = companies.filter(c => c.logo || c.image).map(c => ({ image: c.logo || c.image, title: c.name, link: c.link?.href ? c.link : c.website }));
    const items = apiItems.length ? apiItems : images.map(image => {
        const company = companies.find(c => c.name?.toLowerCase() === image.title.toLowerCase());
        return { ...image, link: company?.link?.href ? company.link : company?.website };
    });
    const [animated, setAnimated] = useState(false);
    const [paused, setPaused] = useState(false);
    const { i18n } = useTranslation();
    const language = i18n.resolvedLanguage?.split('-')[0];
    const labels = language === 'tr' ? ['Şirket görselleri', 'Animasyonu duraklat', 'Animasyonu oynat']
        : language === 'en' ? ['Company images', 'Pause animation', 'Play animation']
            : ['Unternehmensbilder', 'Animation pausieren', 'Animation abspielen'];
    useEffect(() => {
        const media = window.matchMedia('(min-width: 768px) and (prefers-reduced-motion: no-preference)');
        const update = () => setAnimated(media.matches);
        update(); media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);
    return <div className="company-image-wall" role="region" aria-label={labels[0]}>
        {animated ? <>
            <div className="company-image-wall__motion">
                <DriftWall items={items} columns={4} tileWidth={260} tileHeight={180}
                    gap={18} radius={10} tilt={10} turn={-10} depth={60}
                    speed={paused ? 0 : 22} parallax={0.15} pauseOnHover
                    dim={1} fade={0.2} lift={24} overlayColor="transparent" />
            </div>
            <button className="company-image-wall__pause" type="button" onClick={() => setPaused(value => !value)}>
                {paused ? labels[2] : labels[1]}
            </button>
        </> : <div className="company-image-wall__static">
            {items.map(item => <SiteLink key={item.image} item={item.link || {}} className="company-image-wall__logo"><img src={item.image} alt={item.title} loading="lazy" decoding="async" width="520" height="360" /></SiteLink>)}
        </div>}
    </div>;
}
