import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/Layouts/AppLayout';
import SeoHead from '@/Components/SeoHead';
import CorporateHero from '@/Components/Home/CorporateHero';
import GroupMetrics from '@/Components/Home/GroupMetrics';
import BusinessAreasSection from '@/Components/Home/BusinessAreasSection';
import CompaniesSection from '@/Components/Home/CompaniesSection';
import ProjectsSection from '@/Components/Home/ProjectsSection';
import GroupNetwork from '@/Components/Home/GroupNetwork';
import GroupVision from '@/Components/Home/GroupVision';
import HomeCallsToAction from '@/Components/Home/HomeCallsToAction';
import '@/../css/pages/corporate-home.css';

export default function Home({ home }) {
    const { props } = usePage();
    const { t } = useTranslation();
    const seo = home.seo;
    const siteName = props.siteShell?.name || 'Ömer Dogan Company GmbH';
    const title = seo.title || `${siteName} | ${t('corporateHome.areasEyebrow')}`;
    const description = seo.description || home.hero.description;

    return <AppLayout>
        <SeoHead title={title} description={description} keywords={seo.keywords}
            ogTitle={seo.ogTitle || title} ogDescription={seo.ogDescription || description}
            image={seo.image || home.hero.image} locale={props.locale}
            origin={props.tenantSeo?.canonicalBaseUrl} />
        <div className="group-home">
            <CorporateHero hero={home.hero} areas={home.businessAreas} contactLink={home.contactLink} />
            <GroupMetrics items={home.metrics} />
            <BusinessAreasSection areas={home.businessAreas} />
            <CompaniesSection companies={home.companies} />
            <ProjectsSection projects={home.projects} />
            <GroupNetwork countries={home.countries} />
            <GroupVision vision={home.vision} />
            <HomeCallsToAction careerLink={home.careerLink} contactLink={home.contactLink} />
        </div>
    </AppLayout>;
}
