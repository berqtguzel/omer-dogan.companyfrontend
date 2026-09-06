import { useTranslation } from 'react-i18next';
import ResponsiveImage from '@/Components/Common/ResponsiveImage';
import SiteLink from '@/Components/Navigation/SiteLink';

export default function ProjectCard({ project, index = 0 }) {
    const { t } = useTranslation();
    return <article className="group-project-card">
        <ResponsiveImage src={project.image} alt={project.name} className={`group-project-card__visual group-project-card__visual--${index % 3}`}>
            <div className="group-project-card__drawing" aria-hidden="true"><span /><span /><span /></div>
        </ResponsiveImage>
        <div className="group-card-meta">{project.sector}{project.location && <span>{project.location}</span>}</div>
        <h3 className="corporate-heading corporate-heading--card">{project.name}</h3>
        <p>{project.description}</p>
        <SiteLink item={project.link} className="group-text-link" aria-label={`${project.name} – ${t('corporateHome.details')}`}>
            {t('corporateHome.details')}<span aria-hidden="true">↗</span>
        </SiteLink>
    </article>;
}
