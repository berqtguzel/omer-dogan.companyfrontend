import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/Layouts/AppLayout';
import SeoHead from '@/Components/SeoHead';
import Container from '@/Components/Common/Container';
import SafeHtml from '@/Components/Common/SafeHtml';
import ResponsiveImage from '@/Components/Common/ResponsiveImage';
import SiteLink from '@/Components/Navigation/SiteLink';
import '@/../css/pages/corporate-document.css';

export default function StaticPage({ document, pageKind }) {
    const { props } = usePage();
    const { t } = useTranslation();
    const title = document.title || t('corporatePages.' + (pageKind || 'page'), { defaultValue: t('corporatePages.page') });
    return <AppLayout>
        <SeoHead title={document.seo.title || title} description={document.seo.description}
            keywords={document.seo.keywords} image={document.image} locale={props.locale} noindex={!document.available}
            alternates={Object.entries(document.alternates || {}).map(([code, href]) => ({ code, href }))} />
        <section className="corporate-section corporate-document"><Container>
            <SiteLink item={props.siteShell.home} className="corporate-document__back">← {t('corporateHome.back')}</SiteLink>
            <h1 className="corporate-heading corporate-heading--hero">{title}</h1>
            {document.image && <ResponsiveImage src={document.image} alt={title} priority />}
            {document.content ? <SafeHtml as="article" html={document.content} className="corporate-prose" /> :
                <p className="corporate-document__empty">{t(pageKind === 'karriere' ? 'corporatePages.noCareers' : 'corporatePages.empty')}</p>}
        </Container></section>
    </AppLayout>;
}
