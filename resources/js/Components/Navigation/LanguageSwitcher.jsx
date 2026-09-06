import { useId, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Cookies from 'js-cookie';
import { buildLocalizedPath } from '@/utils/localizedPath';

export default function LanguageSwitcher({ locale, languages, label, onNavigate }) {
    const id = useId();
    const [pending, setPending] = useState(false);
    const { props } = usePage();
    const localized = props.localizedUrls ?? props.document?.alternates;
    if (localized && Object.keys(localized).length) languages = languages.filter(language => localized[language.code] || language.code === locale);
    if (languages.length < 2) return null;

    const change = (event) => {
        const code = event.target.value;
        if (code === locale) return;
        const href = `${localized?.[code] || buildLocalizedPath(window.location.pathname, code)}${window.location.search}${window.location.hash}`;
        setPending(true);
        Cookies.set('locale', code, { path: '/', expires: 365, sameSite: 'lax' });
        // app.jsx synchronizes i18n from the destination response, avoiding an
        // intermediate language change on the current page.
        router.visit(href, {
            preserveState: false,
            onSuccess: onNavigate,
            onFinish: () => setPending(false),
        });
    };
    return <div className="corporate-language">
        <label htmlFor={id} className="corporate-visually-hidden">{label}</label>
        <select id={id} value={locale} onChange={change} disabled={pending} aria-busy={pending}>
            {!languages.some((language) => language.code === locale) && <option value={locale}>{locale.toUpperCase()}</option>}
            {languages.map((language) => <option key={language.code} value={language.code}>
                {language.label || language.name || language.code.toUpperCase()}
            </option>)}
        </select>
    </div>;
}
