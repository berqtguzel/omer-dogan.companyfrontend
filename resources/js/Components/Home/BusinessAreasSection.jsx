import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SectionHeader from '@/Components/Common/SectionHeader';
import BusinessAreaCard from '@/Components/BusinessAreas/BusinessAreaCard';

export default function BusinessAreasSection({ areas }) {
    const { t } = useTranslation();
    if (!areas.length) return null;
    return <section id="services" className="corporate-section group-areas" aria-labelledby="areas-title">
        <Container><SectionHeader id="areas-title" eyebrow={t('corporateHome.areasEyebrow')} title={t('corporateHome.areasTitle')} description={t('corporateHome.areasDescription')} />
            <div className="corporate-grid corporate-grid--four">{areas.map((area, index) => <BusinessAreaCard key={area.id} area={area} index={index} />)}</div>
        </Container>
    </section>;
}
