import { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FiCheck, FiInbox, FiMail, FiPaperclip, FiSend, FiX } from 'react-icons/fi';
import '@/../css/NewsletterModal.css';

const DISMISSED_KEY = 'newsletter_modal_dismissed_at';
const SUBSCRIBED_KEY = 'newsletter_modal_subscribed';
const DISMISS_FOR_MS = 7 * 24 * 60 * 60 * 1000;

function wasRecentlyDismissed() {
    try {
        if (localStorage.getItem(SUBSCRIBED_KEY) === '1') return true;
        const dismissedAt = Number(localStorage.getItem(DISMISSED_KEY));
        return Number.isFinite(dismissedAt) && Date.now() - dismissedAt < DISMISS_FOR_MS;
    } catch {
        return false;
    }
}

export default function NewsletterModal({ enabled, site, locale = 'de' }) {
    const { t } = useTranslation();
    const titleId = useId();
    const dialogRef = useRef(null);
    const emailRef = useRef(null);
    const [visible, setVisible] = useState(false);
    const [email, setEmail] = useState('');
    const [consent, setConsent] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState(false);
    const usesMacWindowChrome = typeof navigator !== 'undefined'
        && /Mac|iPhone|iPad|iPod/i.test(navigator.userAgentData?.platform || navigator.platform || navigator.userAgent);

    useEffect(() => {
        if (!enabled || wasRecentlyDismissed()) return undefined;
        const timer = window.setTimeout(() => setVisible(true), 850);
        return () => window.clearTimeout(timer);
    }, [enabled]);

    useEffect(() => {
        if (!visible) return undefined;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const focusTimer = window.setTimeout(() => emailRef.current?.focus(), 120);
        const handleKeyDown = event => {
            if (event.key === 'Escape') close();
            if (event.key !== 'Tab') return;
            const focusable = dialogRef.current?.querySelectorAll('button, a, input:not([disabled])');
            if (!focusable?.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            window.clearTimeout(focusTimer);
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [visible]);

    const close = () => {
        try { localStorage.setItem(DISMISSED_KEY, String(Date.now())); } catch {}
        setVisible(false);
    };

    const submit = async event => {
        event.preventDefault();
        setError('');
        if (!consent) {
            setError(t('newsletter.consent_error'));
            return;
        }
        setBusy(true);
        try {
            const response = await fetch('/api/newsletter/subscribe', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, consent, locale }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || t('newsletter.error'));
            try { localStorage.setItem(SUBSCRIBED_KEY, '1'); } catch {}
            setSuccess(true);
        } catch (requestError) {
            setError(requestError.message || t('newsletter.error'));
        } finally {
            setBusy(false);
        }
    };

    if (!visible) return null;

    const privacyHref = `/${locale}/datenschutz`;
    const mailLabels = {
        de: { inbox: 'Posteingang', from: 'Von', to: 'An', now: 'Jetzt', subject: 'Betreff' },
        en: { inbox: 'Inbox', from: 'From', to: 'To', now: 'Now', subject: 'Subject' },
        tr: { inbox: 'Gelen Kutusu', from: 'Kimden', to: 'Kime', now: 'Şimdi', subject: 'Konu' },
    }[locale] || { inbox: 'Inbox', from: 'From', to: 'To', now: 'Now', subject: 'Subject' };

    return <div className="newsletter-modal" role="presentation" onMouseDown={event => event.target === event.currentTarget && close()}>
        <section ref={dialogRef} className={`newsletter-modal__dialog ${usesMacWindowChrome ? 'is-mac' : 'is-windows'}`} role="dialog" aria-modal="true" aria-labelledby={titleId}>
            <header className="newsletter-modal__toolbar">
                {usesMacWindowChrome ? <div className="newsletter-modal__window-dots">
                    <button type="button" onClick={close} aria-label={t('newsletter.close')} />
                    <i aria-hidden="true" />
                    <i aria-hidden="true" />
                </div> : <span aria-hidden="true" />}
                <div className="newsletter-modal__mailbox"><FiInbox aria-hidden="true" /><span>{mailLabels.inbox}</span></div>
                {usesMacWindowChrome ? <span aria-hidden="true" /> : <div className="newsletter-modal__windows-controls">
                    <span className="newsletter-modal__minimize" aria-hidden="true" />
                    <span className="newsletter-modal__maximize" aria-hidden="true" />
                    <button type="button" className="newsletter-modal__close" onClick={close} aria-label={t('newsletter.close')}><FiX /></button>
                </div>}
            </header>

            <div className="newsletter-modal__message">
                <div className="newsletter-modal__sender">
                    <div className="newsletter-modal__avatar" aria-hidden="true">
                        {site?.logo ? <img src={site.logo} alt="" /> : <FiMail />}
                    </div>
                    <div className="newsletter-modal__sender-copy">
                        <strong>{site?.name || t('newsletter.eyebrow')}</strong>
                        <span>{mailLabels.from}: {t('newsletter.eyebrow')}</span>
                    </div>
                    <time>{mailLabels.now}</time>
                </div>

                {success ? <div className="newsletter-modal__success" role="status">
                    <span><FiCheck /></span>
                    <p>{t('newsletter.success_eyebrow')}</p>
                    <h2 id={titleId}>{t('newsletter.success_title')}</h2>
                    <button type="button" onClick={() => setVisible(false)}>{t('newsletter.done')}</button>
                </div> : <>
                    <form onSubmit={submit} noValidate>
                        <label className="newsletter-modal__recipient" htmlFor="newsletter-email">
                            <span>{mailLabels.to}</span>
                            <input ref={emailRef} id="newsletter-email" type="email" value={email} onChange={event => setEmail(event.target.value)} placeholder={t('newsletter.email')} autoComplete="email" required />
                        </label>
                        <div className="newsletter-modal__subject"><span>{mailLabels.subject}</span><strong>{t('newsletter.title')}</strong></div>

                        <div className="newsletter-modal__body">
                            <p className="newsletter-modal__eyebrow">{t('newsletter.eyebrow')}</p>
                            <h2 id={titleId}>{t('newsletter.title')}</h2>
                            <p className="newsletter-modal__intro">{t('newsletter.description')}</p>
                            <div className="newsletter-modal__signature">
                                <span>{t('newsletter.brand_line')}</span>
                                {site?.logo ? <img src={site.logo} alt={site?.name || ''} /> : <strong>{site?.name}</strong>}
                            </div>
                        </div>

                        <div className="newsletter-modal__compose-footer">
                            <label className="newsletter-modal__consent"><input type="checkbox" checked={consent} onChange={event => setConsent(event.target.checked)} /><span>{t('newsletter.consent_prefix')} <a href={privacyHref} target="_blank" rel="noreferrer">{t('newsletter.privacy')}</a>.</span></label>
                            {error && <p className="newsletter-modal__error" role="alert">{error}</p>}
                            <div className="newsletter-modal__actions">
                                <button type="button" className="newsletter-modal__later" onClick={close}>{t('newsletter.later')}</button>
                                <span className="newsletter-modal__attachment" aria-hidden="true"><FiPaperclip /></span>
                                <button className="newsletter-modal__submit" type="submit" disabled={busy}><span>{busy ? t('newsletter.sending') : t('newsletter.submit')}</span><FiSend /></button>
                            </div>
                        </div>
                    </form>
                </>}
            </div>
        </section>
    </div>;
}
