import { useEffect, useRef } from 'react';
import NavigationTree from './NavigationTree';
import LanguageSwitcher from './LanguageSwitcher';
import SiteLink from './SiteLink';

export default function MobileMenu({ open, onClose, site, labels, locale, languages }) {
    const dialog = useRef(null);
    useEffect(() => {
        const element = dialog.current;
        if (!open) return;
        const previous = document.activeElement;
        const overflow = document.body.style.overflow;
        element.showModal();
        document.body.style.overflow = 'hidden';
        const breakpoint = window.matchMedia('(min-width: 60rem)');
        const resized = () => { if (breakpoint.matches) onClose(); };
        breakpoint.addEventListener('change', resized);
        return () => {
            breakpoint.removeEventListener('change', resized);
            element.close();
            document.body.style.overflow = overflow;
            if (previous instanceof HTMLElement && previous.isConnected) previous.focus();
        };
    }, [open, onClose]);

    return <dialog ref={dialog} id="corporate-mobile-menu" className="corporate-drawer"
        aria-labelledby="corporate-mobile-title" onCancel={onClose}
        onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <div className="corporate-drawer__inner">
            <div className="corporate-drawer__top">
                <span id="corporate-mobile-title">{site.name}</span>
                <button type="button" onClick={onClose} className="corporate-icon-button" aria-label={labels.close} autoFocus>×</button>
            </div>
            <nav aria-label={labels.navigation}>
                <NavigationTree items={site.header} labels={labels} onNavigate={onClose} />
            </nav>
            <div className="corporate-drawer__bottom">
                <LanguageSwitcher locale={locale} languages={languages} label={labels.language} onNavigate={onClose} />
                <SiteLink item={site.contactLink} className="corporate-button" onClick={onClose}
                    data-track-key="mobile_contact_click" data-track-location="mobile-menu" data-track-action="navigate">
                    {labels.contact}<span aria-hidden="true">↗</span>
                </SiteLink>
            </div>
        </div>
    </dialog>;
}
