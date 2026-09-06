import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SiteLink from '@/Components/Navigation/SiteLink';

export default function HomeCallsToAction({ careerLink, contactLink }) {
    const { t } = useTranslation();
    return <>
        {careerLink && <section className="corporate-section group-career" aria-labelledby="career-title"><Container className="group-split">
            <div><p className="group-eyebrow">{t('corporateHome.careerEyebrow')}</p><h2 id="career-title" className="corporate-heading corporate-heading--section">{t('corporateHome.careerTitle')}</h2></div>
            <div><p className="group-lead">{t('corporateHome.careerDescription')}</p><SiteLink item={careerLink} className="group-text-link" data-track-key="home_career_click" data-track-location="home" data-track-action="navigate">{t('corporateHome.careerButton')}<span aria-hidden="true">↗</span></SiteLink></div>
        </Container></section>}
        <section id="main-dialog" className="corporate-section group-contact" aria-labelledby="contact-title"><Container>
            <p className="group-eyebrow">{t('corporateHome.contactEyebrow')}</p><h2 id="contact-title" className="corporate-heading corporate-heading--section">{t('corporateHome.contactTitle')}</h2>
            <p className="group-lead">{t('corporateHome.contactDescription')}</p>
            <SiteLink item={contactLink} className="corporate-button" data-track-key="home_contact_click" data-track-location="home" data-track-action="navigate">{t('corporateHome.contact')}<span aria-hidden="true">↗</span></SiteLink>
        </Container></section>
    </>;
}
