import Container from '@/Components/Common/Container';
import Brand from './Brand';
import SiteLink from './SiteLink';
import '@/../css/components/footer.css';

function FooterLinks({ items, labels }) {
    return <ul className="corporate-footer__links">{items.map((item, index) => <li key={`${item.id}-${index}`}>
        <SiteLink item={item} newTabLabel={labels.newTab} />
        {item.children.length > 0 && <FooterLinks items={item.children} labels={labels} />}
    </li>)}</ul>;
}

export default function Footer({ site, labels, year }) {
    const groups = site.footer.filter((item) => item.children.length);
    const links = site.footer.filter((item) => !item.children.length);
    return <footer className="corporate-footer">
        <Container>
            <div className="corporate-footer__intro">
                <div><Brand site={site} footer />{site.description && <p className="corporate-footer__description">{site.description}</p>}</div>
                <SiteLink item={site.contactLink} className="corporate-footer__cta" data-track-key="footer_contact_click"
                    data-track-location="footer" data-track-action="navigate">{labels.contact}<span aria-hidden="true">↗</span></SiteLink>
            </div>
            <div className="corporate-footer__grid">
                {groups.map((group, index) => <nav key={`${group.id}-${index}`} aria-label={group.label}>
                    <h2 className="corporate-footer__heading"><SiteLink item={group} newTabLabel={labels.newTab} /></h2>
                    <FooterLinks items={group.children} labels={labels} />
                </nav>)}
                {links.length > 0 && <nav aria-label={labels.company}>
                    <h2 className="corporate-footer__heading">{labels.company}</h2>
                    <FooterLinks items={links} labels={labels} />
                </nav>}
                <div>
                    <h2 className="corporate-footer__heading">{labels.contact}</h2>
                    <address className="corporate-footer__address">
                        <span>{site.name}</span>
                        {site.address && <span>{site.address}</span>}
                        {site.phoneHref && <a href={site.phoneHref} data-track-key="footer_phone_click" data-track-name="Footer – Telefon"
                            data-track-location="footer" data-track-action="call">{site.phone}</a>}
                        {site.emailHref && <a href={site.emailHref} data-track-key="footer_email_click" data-track-name="Footer – E-Mail"
                            data-track-location="footer" data-track-action="email">{site.email}</a>}
                    </address>
                </div>
            </div>
            <div className="corporate-footer__bottom">
                <small>© {year} {site.name}</small>
                {site.social.length > 0 && <ul className="corporate-footer__social">{site.social.map((item) => <li key={item.label}>
                    <SiteLink item={item} newTabLabel={labels.newTab} />
                </li>)}</ul>}
            </div>
        </Container>
    </footer>;
}
