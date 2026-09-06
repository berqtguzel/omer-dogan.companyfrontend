import { useTranslation } from 'react-i18next';
import ResponsiveImage from '@/Components/Common/ResponsiveImage';
import SiteLink from '@/Components/Navigation/SiteLink';

export default function CompanyCard({ company }) {
    const { t } = useTranslation();
    return <article className="group-company-card">
        <div className="group-company-card__brand">{company.image
            ? <ResponsiveImage src={company.image} alt={company.name}><span>{company.name}</span></ResponsiveImage>
            : <span>{company.name}</span>}</div>
        <div className="group-company-card__body">
            <p className="group-card-meta">{company.sector}</p>
            <h3 className="corporate-heading corporate-heading--card">{company.name}</h3>
            {company.location && <p className="group-card-location">{company.location}</p>}
            <p>{company.description}</p>
            <SiteLink item={company.link} className="group-text-link" aria-label={`${company.name} – ${t('corporateHome.details')}`}>
                {t('corporateHome.details')}<span aria-hidden="true">↗</span>
            </SiteLink>
            {company.website?.href && <SiteLink item={company.website} className="group-text-link" newTabLabel={t('corporateCatalog.newTab')}>
                {t('corporateCatalog.website')}
            </SiteLink>}
        </div>
    </article>;
}
