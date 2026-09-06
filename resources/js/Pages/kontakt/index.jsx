import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/Layouts/AppLayout';
import SeoHead from '@/Components/SeoHead';
import Container from '@/Components/Common/Container';
import ContactForm from '@/Components/Contact/ContactForm';
import '@/../css/pages/corporate-document.css';

export default function ContactIndex({ forms = [] }) {
    const { props } = usePage();
    const { t } = useTranslation();
    const site = props.siteShell;
    return <AppLayout>
        <SeoHead title={t('corporatePages.contact')} locale={props.locale} />
        <section className="corporate-section corporate-document"><Container>
            <h1 className="corporate-heading corporate-heading--hero">{t('corporatePages.contact')}</h1>
            <div className="corporate-contact-grid">
                <div className="corporate-contact-details">
                    {site.phoneHref && <a href={site.phoneHref} data-track-key="contact_section_phone_click" data-track-action="call">{site.phone}</a>}
                    {site.emailHref && <a href={site.emailHref} data-track-key="contact_section_email_click" data-track-action="email">{site.email}</a>}
                    {site.address && <address>{site.address}</address>}
                </div>
                <ContactForm key={props.locale} form={forms[0]} locale={props.locale} tenantId={props.tenantId} />
            </div>
        </Container></section>
    </AppLayout>;
}
