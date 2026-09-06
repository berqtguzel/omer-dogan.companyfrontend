import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Container from '@/Components/Common/Container';
import Brand from './Brand';
import SiteLink from './SiteLink';
import NavigationTree from './NavigationTree';
import LanguageSwitcher from './LanguageSwitcher';
import MobileMenu from './MobileMenu';
import '@/../css/components/navigation.css';

export default function Header({ site, labels, locale, languages }) {
    const { url, component } = usePage();
    const root = useRef(null);
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const close = useCallback(() => setOpen(false), []);
    const closeBranches = useCallback(() => {
        root.current?.querySelectorAll('details[open]').forEach((branch) => branch.removeAttribute('open'));
    }, []);
    useEffect(() => { close(); closeBranches(); }, [url, close, closeBranches]);
    useEffect(() => {
        const outside = (event) => { if (!root.current?.contains(event.target)) closeBranches(); };
        document.addEventListener('pointerdown', outside);
        return () => document.removeEventListener('pointerdown', outside);
    }, [closeBranches]);
    useEffect(() => {
        const update = () => setScrolled(window.scrollY > 24);
        update();
        window.addEventListener('scroll', update, { passive: true });
        return () => window.removeEventListener('scroll', update);
    }, []);
    const escape = (event) => {
        if (event.key !== 'Escape') return;
        const branch = event.target.closest('details[open]');
        if (branch) { branch.removeAttribute('open'); branch.querySelector('summary').focus(); }
    };
    const cinematic = component === 'Home';
    return <header ref={root} className={`corporate-header${cinematic ? ' corporate-header--overlay' : ''}${scrolled ? ' is-scrolled' : ''}`} onKeyDown={escape}>
        <Container>
            <div className="corporate-header__main">
                <Brand site={site} />
                <span className="corporate-header__descriptor">{labels.company}</span>
                <div className="corporate-header__actions">
                    <LanguageSwitcher locale={locale} languages={languages} label={labels.language} />
                    <SiteLink item={site.contactLink} className="corporate-button" data-track-key="header_contact_click"
                        data-track-name={labels.contact} data-track-location="header" data-track-action="navigate">
                        {labels.contact}<span aria-hidden="true">↗</span>
                    </SiteLink>
                </div>
                <button type="button" className="corporate-icon-button corporate-header__toggle"
                    aria-label={labels.menu} aria-controls="corporate-mobile-menu" aria-expanded={open}
                    onClick={() => setOpen(true)}><span className="corporate-menu-icon" aria-hidden="true" /></button>
            </div>
            <nav className="corporate-header__nav" aria-label={labels.navigation}
                onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) closeBranches(); }}>
                <NavigationTree items={site.header} labels={labels} onNavigate={closeBranches} />
            </nav>
        </Container>
        <MobileMenu open={open} onClose={close} site={site} labels={labels} locale={locale} languages={languages} />
    </header>;
}
