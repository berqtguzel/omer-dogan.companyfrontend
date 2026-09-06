import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SectionHeader from '@/Components/Common/SectionHeader';
import WorldGlobe from '@/Components/Home/WorldGlobe';

export default function GroupNetwork({ countries }) {
    const { t } = useTranslation();
    const [active, setActive] = useState(countries[0]?.code || 'DE');
    if (!countries.length) return null;
    return <section id="locations" className="corporate-section group-network" aria-labelledby="network-title">
        <Container>
            <div className="group-network__intro"><SectionHeader id="network-title" eyebrow={t('corporateHome.networkEyebrow')} title={t('corporateHome.networkTitle')} description={t('corporateHome.networkDescription')} /></div>
            <div className="group-network__layout">
            <div className="group-map" aria-label={t('corporateHome.networkTitle')}>
                <WorldGlobe countries={countries} active={active} />
                <div className="group-map__legend"><span />{countries.find(country => country.code === active)?.name}</div>
            </div>
            <ul className="group-network__countries">{countries.map(country => <li key={country.code} className={active === country.code ? 'is-active' : ''} onMouseEnter={() => setActive(country.code)}>
                <span className="group-network__code" aria-hidden="true">{country.code}</span>
                <div><h3>{country.name}</h3><p>{country.description}</p></div>
            </li>)}</ul>
            </div>
        </Container>
    </section>;
}
