import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SectionHeader from '@/Components/Common/SectionHeader';
import ResponsiveImage from '@/Components/Common/ResponsiveImage';
import SiteLink from '@/Components/Navigation/SiteLink';

export default function GroupVision({ vision }) {
    const { t } = useTranslation();
    const values = vision.values;
    if (!vision.title && !vision.description) return null;
    return <section className="corporate-section group-vision" id="group-vision" aria-labelledby="vision-title">
        <Container className="group-split">
            <div><SectionHeader id="vision-title" eyebrow={t('corporateHome.visionEyebrow')} title={vision.title} description={vision.description} />
                {vision.link && <SiteLink item={vision.link} className="group-text-link">{t('corporateHome.discover')}<span aria-hidden="true">↗</span></SiteLink>}
            </div>
            <div>{vision.image && <ResponsiveImage src={vision.image} alt={vision.title || t('corporateHome.visionEyebrow')} />}
                <ol className="group-vision__values">{values.map((value, index) => <li key={value}><span className="group-number">0{index + 1}</span><h3>{value}</h3></li>)}</ol>
            </div>
        </Container>
    </section>;
}
