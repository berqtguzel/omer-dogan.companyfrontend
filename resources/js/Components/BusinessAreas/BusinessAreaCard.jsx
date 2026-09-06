import { useTranslation } from 'react-i18next';
import ResponsiveImage from '@/Components/Common/ResponsiveImage';
import SiteLink from '@/Components/Navigation/SiteLink';

export default function BusinessAreaCard({ area, index }) {
    const { t } = useTranslation();
    const title = area.title || t(`corporateHome.areas.${area.id}.title`);
    return <article id={`area-${area.id}`} className="group-area-card">
        <div className="group-area-card__top"><span className="group-number">0{index + 1}</span><span className="group-area-card__line" aria-hidden="true" /></div>
        {area.image && <ResponsiveImage src={area.image} alt={title} />}
        <h3 className="corporate-heading corporate-heading--card">{title}</h3>
        <p>{area.description || t(`corporateHome.areas.${area.id}.description`)}</p>
        {area.count > 0 && <p className="group-card-meta">{area.count} {t('corporateHome.sectorCount')}</p>}
        {area.link && <SiteLink item={area.link} className="group-text-link" aria-label={`${title} – ${t('corporateHome.details')}`}>
            {t('corporateHome.details')}<span aria-hidden="true">↗</span>
        </SiteLink>}
    </article>;
}
