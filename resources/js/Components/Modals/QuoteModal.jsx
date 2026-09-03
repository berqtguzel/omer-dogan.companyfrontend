import React, { useEffect, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";
import { createPortal } from "react-dom";
import { useTranslation } from "react-i18next";
import "../../../css/quote-modal.css";
import SuccessModal from "../SuccesModal";
import { trackConversion } from "@/utils/analyticsQueue";

export default function QuoteModal() {
    const { t } = useTranslation();
    const { props } = usePage();

    const global = props.global || {};
    const tenantId = props.tenantId || props.tenant_id || "";
    const locale = props.locale || "de";

    const initialForms = Array.isArray(props.forms) ? props.forms : [];
    const [forms, setForms] = useState(initialForms);
    const categories = global.categories || [];

    const formToDisplay = forms.find((f) => f.id == 2);
    const fields = Array.isArray(formToDisplay?.fields)
        ? formToDisplay.fields
        : [];

    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [ok, setOk] = useState(false);
    const [formState, setFormState] = useState({});
    const [errors, setErrors] = useState({});

    const dialogRef = useRef(null);
    const openerRef = useRef(null);

    const labelKeyMap = {
        field_0: "quote_modal.field_name",
        field_1: "quote_modal.field_email",
        field_2: "quote_modal.field_phone",
        field_3: "quote_modal.field_service",
        field_4: "quote_modal.field_message",
    };

    // 🔥 CRITICAL FIX → EVENT HER SAYFADA GARANTİ ÇALIŞIR
    useEffect(() => {
        const listener = () => {
            openerRef.current = document.activeElement;
            setOpen(true);
        };

        window.addEventListener("open-quote-modal", listener);

        return () => {
            window.removeEventListener("open-quote-modal", listener);
        };
    }, []);

    useEffect(() => {
        if (!open || forms.length > 0) return;

        let cancelled = false;

        fetch(`/api/contact/forms?locale=${locale}`, {
            headers: {
                Accept: "application/json",
                "X-Tenant-ID": tenantId,
            },
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((result) => {
                if (cancelled) return;
                const nextForms = Array.isArray(result?.data)
                    ? result.data
                    : Array.isArray(result)
                      ? result
                      : [];
                setForms(nextForms);
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [open, forms.length, locale, tenantId]);

    const resetForm = () => {
        setFormState({});
        setErrors({});
    };

    const close = () => {
        setOpen(false);
        setOk(false);
        resetForm();
        openerRef.current?.focus?.();
    };

    const setField = (name, value) => {
        let updated = value;

        if (name === "field_2" && !/^[0-9+\-()\s]*$/.test(value)) return;
        if (name === "field_1") updated = value.toLowerCase();

        setFormState((prev) => ({ ...prev, [name]: updated }));

        if (errors[name]) {
            const copy = { ...errors };
            delete copy[name];
            setErrors(copy);
        }
    };

    const submitForm = async () => {
        const payload = {
            name: formState.field_0 || "",
            email: formState.field_1 || "",
            phone: formState.field_2 || "",
            service: formState.field_3 || "",
            message: formState.field_4 || "",
        };

        const response = await fetch(
            `/api/contact/forms/${formToDisplay.id}/submit?locale=${locale}`,
            {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Tenant-ID": tenantId,
                },
                body: JSON.stringify(payload),
            },
        );

        const result = await response.json();
        return { status: response.status, data: result };
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        const frontendErrors = {};

        if (!formState.field_0)
            frontendErrors["field_0"] = t("contact.required.name");
        if (!formState.field_1)
            frontendErrors["field_1"] = t("contact.required.email");
        if (!formState.field_2)
            frontendErrors["field_2"] = t("contact.required.phone");
        if (!formState.field_4)
            frontendErrors["field_4"] = t("contact.required.message");

        if (Object.keys(frontendErrors).length > 0) {
            setErrors(frontendErrors);
            setSubmitting(false);
            return;
        }

        try {
            const { status, data } = await submitForm();

            if (status === 422 && data.errors) {
                const beErrors = {};

                Object.keys(data.errors).forEach((key) => {
                    if (key === "name")
                        beErrors["field_0"] = t("contact.required.name");
                    if (key === "email" || key === "e-mail")
                        beErrors["field_1"] = t("contact.required.email");
                    if (key === "phone" || key === "telefon")
                        beErrors["field_2"] = t("contact.required.phone");
                    if (key === "message" || key === "nachricht")
                        beErrors["field_4"] = t("contact.required.message");
                });

                setErrors(beErrors);
                setSubmitting(false);
                return;
            }

            if (status >= 200 && status < 300) {
                trackConversion("quote_form_submitted", {
                    form_id: formToDisplay.id,
                    service: formState.field_3 || null,
                });
            }

            setOk(true);
            resetForm();
        } catch {
            setOk(true);
            resetForm();
        } finally {
            setSubmitting(false);
        }
    };

    if (!open || !formToDisplay) return null;

    return createPortal(
        <div className="qdock" style={{ zIndex: 99999 }}>
            <button className="qdock__scrim" onClick={close} />

            <div className="qdock__dialog qdock-anim-in" ref={dialogRef}>
                {!ok ? (
                    <form className="qdock__form" onSubmit={onSubmit}>
                        <div className="qdock__grid">
                            {fields.map((f) => (
                                <div
                                    key={f.name}
                                    className={`qdock__field-box ${f.type === "textarea" ? "full" : ""}`}
                                >
                                    <label className="qdock__field">
                                        <span>
                                            {t(labelKeyMap[f.name] || f.label)}{" "}
                                            {f.required && "*"}
                                        </span>

                                        {f.type === "select" ? (
                                            <select
                                                name={f.name}
                                                value={formState[f.name] || ""}
                                                onChange={(e) =>
                                                    setField(
                                                        f.name,
                                                        e.target.value,
                                                    )
                                                }
                                                className={
                                                    errors[f.name]
                                                        ? "error"
                                                        : ""
                                                }
                                            >
                                                <option value="">
                                                    {t(
                                                        "quote_modal.service_placeholder",
                                                        "Lütfen seçin",
                                                    )}
                                                </option>
                                                {categories.map((cat) => (
                                                    <option
                                                        key={cat.id}
                                                        value={cat.slug}
                                                    >
                                                        {cat.name || cat.slug}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : f.type === "textarea" ? (
                                            <textarea
                                                name={f.name}
                                                rows={4}
                                                value={formState[f.name] || ""}
                                                onChange={(e) =>
                                                    setField(
                                                        f.name,
                                                        e.target.value,
                                                    )
                                                }
                                                className={
                                                    errors[f.name]
                                                        ? "error"
                                                        : ""
                                                }
                                            />
                                        ) : (
                                            <input
                                                name={f.name}
                                                type={
                                                    f.name === "field_2"
                                                        ? "tel"
                                                        : f.name === "field_1"
                                                          ? "email"
                                                          : "text"
                                                }
                                                value={formState[f.name] || ""}
                                                onChange={(e) =>
                                                    setField(
                                                        f.name,
                                                        e.target.value,
                                                    )
                                                }
                                                className={
                                                    errors[f.name]
                                                        ? "error"
                                                        : ""
                                                }
                                            />
                                        )}

                                        {errors[f.name] && (
                                            <span className="qdock__error">
                                                {errors[f.name]}
                                            </span>
                                        )}
                                    </label>
                                </div>
                            ))}
                        </div>

                        <div className="qdock__actions">
                            <button
                                type="button"
                                onClick={close}
                                className="btn btn--ghost"
                            >
                                {t("quote_modal.cancel", "Vazgeç")}
                            </button>
                            <button
                                type="submit"
                                className="btn btn--primary anfordern-form-submit-button"
                                data-track-key="anfordern_form_submit"
                                data-track-name="Anfordern – Formular senden"
                                data-track-location="contact_form_modal"
                                data-track-action="submit_contact_form"
                                data-track-form="anfordern_form"
                                disabled={submitting}
                            >
                                {submitting
                                    ? "..."
                                    : t("quote_modal.submit", "Gönder")}
                            </button>
                        </div>
                    </form>
                ) : (
                    <div className="qdock__ok">
                        <div className="qdock__ok-badge">✓</div>
                        <h3>
                            {t("quote_modal.thank_you_title", "Teşekkürler!")}
                        </h3>
                        <p>
                            {t(
                                "quote_modal.thank_you_text",
                                "Talebinizi aldık, en kısa sürede sizinle iletişime geçeceğiz.",
                            )}
                        </p>
                        <button className="btn btn--primary" onClick={close}>
                            {t("quote_modal.close_button", "Kapat")}
                        </button>
                    </div>
                )}
            </div>
        </div>,
        document.body,
    );
}
