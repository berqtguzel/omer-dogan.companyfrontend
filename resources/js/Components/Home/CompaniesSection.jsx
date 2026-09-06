import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SectionHeader from '@/Components/Common/SectionHeader';
import CompanyCard from '@/Components/Companies/CompanyCard';

export default function CompaniesSection({ companies }) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState('');
    if (!companies.length) return null;
    const sectors = [...new Set(companies.map(company => company.sector).filter(Boolean))];
    const active = sectors.includes(selected) ? selected : '';
    const visible = active ? companies.filter(company => company.sector === active) : companies;
    return <section className="corporate-section group-companies" id="companies" aria-labelledby="companies-title">
        <Container><SectionHeader id="companies-title" eyebrow={t('corporateHome.companiesEyebrow')} title={t('corporateHome.companiesTitle')} description={t('corporateHome.companiesDescription')} />
            {sectors.length > 1 && <div className="group-filters" role="group" aria-label={t('corporateHome.companiesEyebrow')}>
                {['', ...sectors].map(sector => <button key={sector} type="button" aria-pressed={active === sector} aria-controls="company-results" onClick={() => setSelected(sector)}>
                    {sector || t('corporateHome.all')}
                </button>)}
            </div>}
            <div id="company-results" className="corporate-grid corporate-grid--four" aria-live="polite" aria-atomic="true">
                {visible.map(company => <CompanyCard key={company.id} company={company} />)}
            </div>
        </Container>
    </section>;
}
