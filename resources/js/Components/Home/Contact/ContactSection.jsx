import React, { useState, useEffect } from "react";
import { usePage } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import "../../../../css/ContactSection.css";
import { FaPhoneAlt, FaEnvelope } from "react-icons/fa";
import SuccessModal from "@/Components/SuccesModal";
import { renderText } from "@/utils/renderValue";
import { trackConversion } from "@/utils/analyticsQueue";
import {
    DEFAULT_CONTACT_INFO,
    contactInfosFromSettings,
} from "@/utils/contactFallback";

const ContactSection = ({ trackContactButton = false }) => {
    const { t } = useTranslation();
    const { props } = usePage();

    const tenantId = props.tenantId || "";
    const locale = props.locale || "de";
    const settings = props.settings || props.global?.settings || {};

    const contactInfo =
        contactInfosFromSettings(settings)[0] || DEFAULT_CONTACT_INFO;

    const initialForms = Array.isArray(props.forms) ? props.forms : [];
    const [forms, setForms] = useState(initialForms);
    const formToDisplay = forms[0];

    const fields = Array.isArray(formToDisplay?.fields)
        ? formToDisplay.fields
        : [];

    const displayPhone = renderText(
        contactInfo.phone,
        DEFAULT_CONTACT_INFO.phone,
    );
    const phoneHref = displayPhone.replace(/[^+\d]/g, "");
    const displayEmail = renderText(
        contactInfo.email,
        DEFAULT_CONTACT_INFO.email,
    );

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [data, setData] = useState({});
    const [successModalOpen, setSuccessModalOpen] = useState(false);

    const labelKeyMap = {
        field_0: "contact.form.name",
        field_1: "contact.form.phone",
        field_2: "contact.form.email",
        field_3: "contact.form.message",
    };
    const fieldConfigMap = {
        field_0: {
            id: "contact-name",
            name: "name",
            type: "text",
            label: "Name",
        },
        field_1: {
            id: "contact-phone",
            name: "phone",
            type: "tel",
            label: "Telefonnummer",
        },
        field_2: {
            id: "contact-email",
            name: "email",
            type: "email",
            label: "E-Mail-Adresse",
        },
        field_3: {
            id: "contact-message",
            name: "message",
            type: "textarea",
            label: "Nachricht",
        },
    };

    useEffect(() => {
        if (forms.length > 0) return;

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
    }, [forms.length, locale, tenantId]);

    const safeSetField = (name, value) => {
        if (name === "field_1" && !/^[0-9+\-()\s]*$/.test(value)) return;
        if (name === "field_2") value = value.toLowerCase();

        setData((prev) => ({ ...prev, [name]: value }));

        if (errors[name]) {
            const newErr = { ...errors };
            delete newErr[name];
            setErrors(newErr);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        const frontendErrors = {};

        if (!data.field_0)
            frontendErrors["field_0"] = t("contact.required.name");
        if (!data.field_1)
            frontendErrors["field_1"] = t("contact.required.phone");
        if (!data.field_2)
            frontendErrors["field_2"] = t("contact.required.email");
        if (!data.field_3)
            frontendErrors["field_3"] = t("contact.required.message");

        if (Object.keys(frontendErrors).length > 0) {
            setErrors(frontendErrors);
            setIsSubmitting(false);
            return;
        }

        const form_fields = {
            name: data.field_0,
            phone: data.field_1,
            email: data.field_2,
            message: data.field_3,
        };

        try {
            const response = await fetch(
                `/api/contact/forms/${formToDisplay.id}/submit?locale=${locale}`,
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Tenant-ID": tenantId,
                    },
                    body: JSON.stringify(form_fields),
                },
            );

            const result = await response.json().catch(() => ({}));

            if (response.status === 422) {
                const translatedErrors = {};
                Object.keys(result.errors || {}).forEach((key) => {
                    if (key === "email")
                        translatedErrors["field_2"] = t(
                            "contact.required.email",
                        );
                    if (key === "phone")
                        translatedErrors["field_1"] = t(
                            "contact.required.phone",
                        );
                    if (key === "message")
                        translatedErrors["field_3"] = t(
                            "contact.required.message",
                        );
                    if (key === "name")
                        translatedErrors["field_0"] = t(
                            "contact.required.name",
                        );
                });

                setErrors(translatedErrors);
                setIsSubmitting(false);
                return;
            }

            if (!response.ok) {
                setErrors({
                    _form:
                        result.message ||
                        t(
                            "contact.submit_failed",
                            "Message could not be sent.",
                        ),
                });
                setIsSubmitting(false);
                return;
            }

            setSuccessModalOpen(true);
            setData({});
            trackConversion("contact_form_submitted", {
                form_id: formToDisplay.id,
            });
        } catch {
            setErrors({
                _form: t("contact.submit_failed", "Message could not be sent."),
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <section className="contact-section rbits-section" id="contact">
            {successModalOpen && (
                <SuccessModal
                    isOpen={successModalOpen}
                    onClose={() => setSuccessModalOpen(false)}
                    title={t("contact.success_title")}
                    message={t("contact.success_message")}
                    subMessage={t("contact.success_sub")}
                    buttonText={t("common.ok")}
                />
            )}

            <div className="contact-container">
                <div className="contact-content">
                    <div className="contact-info">
                        <h2>{t("contact.title")}</h2>
                        <div className="contact-details">
                            {displayPhone && phoneHref && (
                                <div className="contact-details-text">
                                    <FaPhoneAlt
                                        aria-hidden="true"
                                        focusable="false"
                                    />{" "}
                                    <a
                                        href={`tel:${phoneHref}`}
                                        data-track-key="contact_section_phone_click"
                                        data-track-name="Kontaktbereich – Telefon"
                                        data-track-location="contact_section"
                                        data-track-action="call"
                                    >
                                        {displayPhone}
                                    </a>
                                </div>
                            )}
                            {displayEmail && <div className="contact-details-text">
                                <FaEnvelope
                                    aria-hidden="true"
                                    focusable="false"
                                />{" "}
                                <a
                                    href={`mailto:${displayEmail}`}
                                    data-track-key="contact_section_email_click"
                                    data-track-name="Kontaktbereich – E-posta"
                                    data-track-location="contact_section"
                                    data-track-action="email"
                                >
                                    {displayEmail}
                                </a>
                            </div>}
                        </div>

                    </div>

                    {formToDisplay && (
                        <form className="contact-form" onSubmit={handleSubmit}>
                            <div className="form-grid">
                                {fields.map((f) => {
                                    const fieldConfig =
                                        fieldConfigMap[f.name] || {};
                                    const fieldId =
                                        fieldConfig.id ||
                                        `contact-${String(f.name).replace(/[^a-z0-9_-]/gi, "-")}`;
                                    const fieldName =
                                        fieldConfig.name || f.name;
                                    const fieldType =
                                        fieldConfig.type || f.type || "text";
                                    const labelText = t(
                                        labelKeyMap[f.name] || f.label,
                                        fieldConfig.label || f.label,
                                    );
                                    const errorId = `${fieldId}-error`;

                                    return (
                                        <div
                                            key={f.name}
                                            className={`form-group ${
                                                fieldType === "textarea"
                                                    ? "full-width"
                                                    : ""
                                            }`}
                                        >
                                            <label htmlFor={fieldId}>
                                                {labelText} {f.required && "*"}
                                            </label>

                                            {fieldType === "textarea" ? (
                                                <textarea
                                                    id={fieldId}
                                                    name={fieldName}
                                                    rows="5"
                                                    value={data[f.name] || ""}
                                                    aria-invalid={
                                                        errors[f.name]
                                                            ? "true"
                                                            : "false"
                                                    }
                                                    aria-describedby={
                                                        errors[f.name]
                                                            ? errorId
                                                            : undefined
                                                    }
                                                    onChange={(e) =>
                                                        safeSetField(
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
                                                    id={fieldId}
                                                    name={fieldName}
                                                    type={fieldType}
                                                    value={data[f.name] || ""}
                                                    aria-invalid={
                                                        errors[f.name]
                                                            ? "true"
                                                            : "false"
                                                    }
                                                    aria-describedby={
                                                        errors[f.name]
                                                            ? errorId
                                                            : undefined
                                                    }
                                                    onChange={(e) =>
                                                        safeSetField(
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
                                                <span
                                                    id={errorId}
                                                    className="error-message"
                                                >
                                                    {errors[f.name]}
                                                </span>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>

                            <button
                                type="submit"
                                className={`submit-button bg-button${
                                    trackContactButton
                                        ? " contact-form-submit-button"
                                        : ""
                                }`}
                                data-track-key={
                                    trackContactButton
                                        ? "contact_form_submit"
                                        : undefined
                                }
                                data-track-name={
                                    trackContactButton
                                        ? "Kontakt – Nachricht senden"
                                        : undefined
                                }
                                data-track-location={
                                    trackContactButton
                                        ? "contact_page"
                                        : undefined
                                }
                                data-track-action={
                                    trackContactButton
                                        ? "submit_contact_form"
                                        : undefined
                                }
                                data-track-form={
                                    trackContactButton
                                        ? "contact_form"
                                        : undefined
                                }
                                disabled={isSubmitting}
                                aria-busy={isSubmitting}
                            >
                                {isSubmitting && (
                                    <span
                                        className="loading-spinner"
                                        aria-hidden="true"
                                    />
                                )}
                                <span>
                                    {isSubmitting
                                        ? t("contact.submitting")
                                        : t("contact.submit_label")}
                                </span>
                            </button>

                            {errors._form && (
                                <span className="error-message">
                                    {errors._form}
                                </span>
                            )}
                        </form>
                    )}
                </div>
            </div>
        </section>
    );
};

export default ContactSection;
