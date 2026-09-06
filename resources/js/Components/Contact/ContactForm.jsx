import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { trackConversion } from '@/utils/analyticsQueue';

// These four submitted fields are the existing contact endpoint contract.
const fields = [ ['name', 'text'], ['phone', 'tel'], ['email', 'email'], ['message', 'textarea'] ];
export default function ContactForm({ form, locale, tenantId }) {
    const { t } = useTranslation();
    const [data, setData] = useState({});
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [success, setSuccess] = useState(false);
    const errorRef = useRef(null);
    if (!form?.id) return <p>{t('corporatePages.noForm')}</p>;
    const submit = async event => {
        event.preventDefault();
        if (busy) return;
        setBusy(true); setErrors({}); setSuccess(false);
        try {
            const response = await fetch(`/api/contact/forms/${encodeURIComponent(form.id)}/submit?locale=${encodeURIComponent(locale)}`, {
                method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Tenant-ID': tenantId || '' },
                body: JSON.stringify(data),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                setErrors({ ...result.errors, _form: t('contact.submit_failed', 'Message could not be sent.') });
                requestAnimationFrame(() => errorRef.current?.focus());
                return;
            }
            setData({}); setSuccess(true);
            trackConversion('contact_form_submitted', { form_id: form.id });
        } catch {
            setErrors({ _form: t('contact.submit_failed', 'Message could not be sent.') });
            requestAnimationFrame(() => errorRef.current?.focus());
        } finally { setBusy(false); }
    };
    return <form className="corporate-contact-form" onSubmit={submit} aria-busy={busy}>
        {errors._form && <p role="alert" tabIndex={-1} ref={errorRef}>{errors._form}</p>}
        {success && <p role="status">{t('contact.success_message')}</p>}
        {fields.map(([name, type]) => {
            const Tag = type === 'textarea' ? 'textarea' : 'input';
            return <label key={name} htmlFor={`contact-${name}`}>{t(`contact.form.${name}`)} *
                <Tag id={`contact-${name}`} name={name} type={type === 'textarea' ? undefined : type} required
                    maxLength={name === 'message' ? 5000 : name === 'phone' ? 50 : 255} rows={type === 'textarea' ? 6 : undefined}
                    autoComplete={name === 'message' ? undefined : name === 'phone' ? 'tel' : name}
                    value={data[name] || ''} onChange={event => setData({ ...data, [name]: event.target.value })}
                    aria-invalid={Boolean(errors[name])} aria-describedby={errors[name] ? `contact-${name}-error` : undefined} />
                {errors[name] && <span id={`contact-${name}-error`}>{Array.isArray(errors[name]) ? errors[name].join(' ') : errors[name]}</span>}
            </label>;
        })}
        <button className="corporate-button" disabled={busy} data-track-key="contact_form_submit" data-track-location="contact_page" data-track-action="submit_contact_form">
            {busy ? t('corporatePages.sending') : t('corporatePages.send')}
        </button>
    </form>;
}
