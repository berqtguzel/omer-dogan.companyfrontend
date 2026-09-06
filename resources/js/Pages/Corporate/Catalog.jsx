import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/Layouts/AppLayout';
import SeoHead from '@/Components/SeoHead';
import Container from '@/Components/Common/Container';
import ResponsiveImage from '@/Components/Common/ResponsiveImage';
import SiteLink from '@/Components/Navigation/SiteLink';
import BusinessAreaCard from '@/Components/BusinessAreas/BusinessAreaCard';
import CompanyCard from '@/Components/Companies/CompanyCard';
import ProjectCard from '@/Components/Projects/ProjectCard';
import '@/../css/pages/corporate-home.css';
import '@/../css/pages/corporate-catalog.css';

function Cards({ kind, items }) {
    return <div className="corporate-grid corporate-grid--three catalog-grid">{items.map((item, index) =>
        kind === 'businessAreas' ? <BusinessAreaCard key={item.id} area={item} index={index} /> :
        kind === 'companies' ? <CompanyCard key={item.id} company={item} /> : <ProjectCard key={item.id} project={item} index={index} />
    )}</div>;
}

function Collection({ catalog }) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [sector, setSector] = useState('');
    const [location, setLocation] = useState('');
    const options = (field) => [...new Set(catalog.items.map(item => item[field]).filter(Boolean))];
    const fold = value => value.toLocaleLowerCase().normalize('NFD').replace(/\p{M}/gu, '');
    const filtered = catalog.items.filter(item => (!sector || item.sector === sector) && (!location || item.location === location) &&
        fold([item.name, item.title, item.description, item.location, item.sector].filter(Boolean).join(' ')).includes(fold(query.trim())));
    const reset = () => { setQuery(''); setSector(''); setLocation(''); };
    return <section className="catalog-collection" aria-labelledby="catalog-results-heading">
        <h2 id="catalog-results-heading" className="corporate-visually-hidden">{t(`corporateCatalog.${catalog.kind}`)}</h2>
        {catalog.items.length > 0 && <div className="catalog-filters">
            <label>{t('corporateCatalog.search')}<input type="search" value={query} onChange={event => setQuery(event.target.value)} /></label>
            {['sector', 'location'].map(field => options(field).length > 1 && <label key={field}>{t(`corporateCatalog.${field}`)}
                <select value={field === 'sector' ? sector : location} onChange={event => (field === 'sector' ? setSector : setLocation)(event.target.value)}>
                    <option value="">{t('corporateHome.all')}</option>{options(field).map(value => <option key={value}>{value}</option>)}
                </select>
            </label>)}
            {(query || sector || location) && <button type="button" className="group-text-link" onClick={reset}>{t('corporateCatalog.reset')}</button>}
        </div>}
        <p className="group-card-meta" role="status">{t('corporateCatalog.results', { count: filtered.length })}</p>
        {filtered.length ? <Cards kind={catalog.kind} items={filtered} /> : <p className="catalog-empty">{t(catalog.items.length ? 'corporateCatalog.noResults' : 'corporateCatalog.empty')}</p>}
    </section>;
}

export default function Catalog({ catalog }) {
    const { props } = usePage();
    const { t } = useTranslation();
    const item = catalog.item;
    const title = item ? (item.title || item.name) : catalog.title || t(`corporateCatalog.${catalog.kind}`);
    const description = item ? item.description : catalog.description;
    return <AppLayout>
        <SeoHead title={`${title} | ${props.siteShell.name}`} description={description} locale={props.locale}
            noindex={!item && !catalog.items.length}
            alternates={Object.entries(item?.alternates || {}).map(([code, href]) => ({ code, href }))} />
        <section className="corporate-section catalog-page"><Container>
            <nav aria-label={t('corporateCatalog.breadcrumb')} className="catalog-breadcrumb">
                <SiteLink item={props.siteShell.home}>{t('corporateHome.back')}</SiteLink>
                <span aria-hidden="true">/</span>
                {item ? <><SiteLink item={catalog.indexLink}>{t(`corporateCatalog.${catalog.kind}`)}</SiteLink><span aria-hidden="true">/</span><span aria-current="page">{title}</span></> : <span aria-current="page">{t(`corporateCatalog.${catalog.kind}`)}</span>}
            </nav>
            <header className="catalog-heading"><p className="group-eyebrow">{t(`corporateCatalog.${catalog.kind}`)}</p>
                <h1 className="corporate-heading corporate-heading--hero">{title}</h1>
                <p className="group-lead">{description}</p>
            </header>
            {item ? <>
                <div className="catalog-detail">
                    <div>
                        {item.image && <ResponsiveImage src={item.image} alt={title} priority />}
                        {item.body && <section><h2 className="corporate-heading corporate-heading--section">{t('corporateCatalog.overview')}</h2><p className="catalog-body">{item.body}</p></section>}
                        {item.highlights?.length > 0 && <section><h2 className="corporate-heading corporate-heading--section">{t('corporateCatalog.focus')}</h2><ul className="catalog-highlights">{item.highlights.map(text => <li key={text}>{text}</li>)}</ul></section>}
                    </div>
                    <aside className="catalog-facts">
                        <h2 className="corporate-heading corporate-heading--card">{t('corporateCatalog.atGlance')}</h2>
                        <dl>{['sector', 'location'].map(field => item[field] && <div key={field}><dt>{t(`corporateCatalog.${field}`)}</dt><dd>{item[field]}</dd></div>)}</dl>
                        <SiteLink item={props.siteShell.contactLink} className="corporate-button">{t('corporateHome.contact')} ↗</SiteLink>
                        {item.website?.href && <SiteLink item={item.website} className="group-text-link" newTabLabel={t('corporateCatalog.newTab')}>{t('corporateCatalog.website')}</SiteLink>}
                    </aside>
                </div>
                {Object.entries(catalog.related).map(([kind, items]) => items.length > 0 && <section className="catalog-related" key={kind}>
                    <p className="group-eyebrow">{t('corporateCatalog.related')}</p><h2 className="corporate-heading corporate-heading--section">{t(`corporateCatalog.${kind}`)}</h2><Cards kind={kind} items={items} />
                </section>)}
            </> : <Collection key={`${props.locale}-${catalog.kind}`} catalog={catalog} />}
        </Container></section>
    </AppLayout>;
}
