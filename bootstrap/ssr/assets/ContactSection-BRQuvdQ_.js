import { jsxs, jsx } from "react/jsx-runtime";
import { useState, useEffect } from "react";
import { usePage } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { FaTimes, FaCheckCircle, FaPhoneAlt, FaEnvelope } from "react-icons/fa";
import { createPortal } from "react-dom";
import { c as renderText, t as trackConversion } from "./AppLayout-CWzTYdDK.js";
const SuccessModal = ({
  isOpen,
  onClose,
  title,
  message,
  subMessage,
  buttonText
}) => {
  if (!isOpen) return null;
  return createPortal(
    /* @__PURE__ */ jsxs("div", { className: "qdock", style: { zIndex: 9999 }, children: [
      /* @__PURE__ */ jsx(
        "button",
        {
          className: "qdock__scrim",
          onClick: onClose,
          "aria-label": "Kapat"
        }
      ),
      /* @__PURE__ */ jsxs(
        "div",
        {
          className: "qdock__dialog qdock-anim-in",
          role: "dialog",
          "aria-modal": "true",
          children: [
            /* @__PURE__ */ jsxs("div", { className: "qdock__head", children: [
              /* @__PURE__ */ jsx("h2", { className: "qdock__title", children: title }),
              /* @__PURE__ */ jsx(
                "button",
                {
                  className: "qdock__close",
                  onClick: onClose,
                  "aria-label": "Kapat",
                  children: /* @__PURE__ */ jsx(FaTimes, {})
                }
              )
            ] }),
            /* @__PURE__ */ jsxs("div", { className: "qdock__ok", children: [
              /* @__PURE__ */ jsx("div", { className: "qdock__ok-badge", "aria-hidden": true, children: /* @__PURE__ */ jsx(FaCheckCircle, {}) }),
              /* @__PURE__ */ jsx("h3", { className: "text-xl font-bold mt-4 mb-2", children: title }),
              /* @__PURE__ */ jsx("p", { className: "text-gray-600 dark:text-gray-300 mb-1", children: message }),
              subMessage && /* @__PURE__ */ jsx("p", { className: "text-sm text-gray-400", children: subMessage }),
              /* @__PURE__ */ jsx(
                "button",
                {
                  className: "btn btn--primary mt-6 w-full",
                  onClick: onClose,
                  children: buttonText
                }
              )
            ] })
          ]
        }
      )
    ] }),
    document.body
  );
};
const DEFAULT_CONTACT_INFO = {
  phone: "",
  email: ""
};
function contactInfosFromSettings(settings = {}) {
  var _a;
  const candidates = [
    (_a = settings == null ? void 0 : settings.contact) == null ? void 0 : _a.contact_infos,
    settings == null ? void 0 : settings.contact_infos
  ];
  const infos = candidates.find(
    (items) => Array.isArray(items) && items.length > 0
  );
  return infos || [];
}
const ContactSection = ({ trackContactButton = false }) => {
  var _a;
  const { t } = useTranslation();
  const { props } = usePage();
  const tenantId = props.tenantId || "";
  const locale = props.locale || "de";
  const settings = props.settings || ((_a = props.global) == null ? void 0 : _a.settings) || {};
  const contactInfo = contactInfosFromSettings(settings)[0] || DEFAULT_CONTACT_INFO;
  const initialForms = Array.isArray(props.forms) ? props.forms : [];
  const [forms, setForms] = useState(initialForms);
  const formToDisplay = forms[0];
  const fields = Array.isArray(formToDisplay == null ? void 0 : formToDisplay.fields) ? formToDisplay.fields : [];
  const displayPhone = renderText(
    contactInfo.phone,
    DEFAULT_CONTACT_INFO.phone
  );
  const phoneHref = displayPhone.replace(/[^+\d]/g, "");
  const displayEmail = renderText(
    contactInfo.email,
    DEFAULT_CONTACT_INFO.email
  );
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [data, setData] = useState({});
  const [successModalOpen, setSuccessModalOpen] = useState(false);
  const labelKeyMap = {
    field_0: "contact.form.name",
    field_1: "contact.form.phone",
    field_2: "contact.form.email",
    field_3: "contact.form.message"
  };
  const fieldConfigMap = {
    field_0: {
      id: "contact-name",
      name: "name",
      type: "text",
      label: "Name"
    },
    field_1: {
      id: "contact-phone",
      name: "phone",
      type: "tel",
      label: "Telefonnummer"
    },
    field_2: {
      id: "contact-email",
      name: "email",
      type: "email",
      label: "E-Mail-Adresse"
    },
    field_3: {
      id: "contact-message",
      name: "message",
      type: "textarea",
      label: "Nachricht"
    }
  };
  useEffect(() => {
    if (forms.length > 0) return;
    let cancelled = false;
    fetch(`/api/contact/forms?locale=${locale}`, {
      headers: {
        Accept: "application/json",
        "X-Tenant-ID": tenantId
      }
    }).then((response) => response.ok ? response.json() : null).then((result) => {
      if (cancelled) return;
      const nextForms = Array.isArray(result == null ? void 0 : result.data) ? result.data : Array.isArray(result) ? result : [];
      setForms(nextForms);
    }).catch(() => {
    });
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
      message: data.field_3
    };
    try {
      const response = await fetch(
        `/api/contact/forms/${formToDisplay.id}/submit?locale=${locale}`,
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Tenant-ID": tenantId
          },
          body: JSON.stringify(form_fields)
        }
      );
      const result = await response.json().catch(() => ({}));
      if (response.status === 422) {
        const translatedErrors = {};
        Object.keys(result.errors || {}).forEach((key) => {
          if (key === "email")
            translatedErrors["field_2"] = t(
              "contact.required.email"
            );
          if (key === "phone")
            translatedErrors["field_1"] = t(
              "contact.required.phone"
            );
          if (key === "message")
            translatedErrors["field_3"] = t(
              "contact.required.message"
            );
          if (key === "name")
            translatedErrors["field_0"] = t(
              "contact.required.name"
            );
        });
        setErrors(translatedErrors);
        setIsSubmitting(false);
        return;
      }
      if (!response.ok) {
        setErrors({
          _form: result.message || t(
            "contact.submit_failed",
            "Message could not be sent."
          )
        });
        setIsSubmitting(false);
        return;
      }
      setSuccessModalOpen(true);
      setData({});
      trackConversion("contact_form_submitted", {
        form_id: formToDisplay.id
      });
    } catch {
      setErrors({
        _form: t("contact.submit_failed", "Message could not be sent.")
      });
    } finally {
      setIsSubmitting(false);
    }
  };
  return /* @__PURE__ */ jsxs("section", { className: "contact-section rbits-section", id: "contact", children: [
    successModalOpen && /* @__PURE__ */ jsx(
      SuccessModal,
      {
        isOpen: successModalOpen,
        onClose: () => setSuccessModalOpen(false),
        title: t("contact.success_title"),
        message: t("contact.success_message"),
        subMessage: t("contact.success_sub"),
        buttonText: t("common.ok")
      }
    ),
    /* @__PURE__ */ jsx("div", { className: "contact-container", children: /* @__PURE__ */ jsxs("div", { className: "contact-content", children: [
      /* @__PURE__ */ jsxs("div", { className: "contact-info", children: [
        /* @__PURE__ */ jsx("h2", { children: t("contact.title") }),
        /* @__PURE__ */ jsxs("div", { className: "contact-details", children: [
          displayPhone && phoneHref && /* @__PURE__ */ jsxs("div", { className: "contact-details-text", children: [
            /* @__PURE__ */ jsx(
              FaPhoneAlt,
              {
                "aria-hidden": "true",
                focusable: "false"
              }
            ),
            " ",
            /* @__PURE__ */ jsx(
              "a",
              {
                href: `tel:${phoneHref}`,
                "data-track-key": "contact_section_phone_click",
                "data-track-name": "Kontaktbereich – Telefon",
                "data-track-location": "contact_section",
                "data-track-action": "call",
                children: displayPhone
              }
            )
          ] }),
          displayEmail && /* @__PURE__ */ jsxs("div", { className: "contact-details-text", children: [
            /* @__PURE__ */ jsx(
              FaEnvelope,
              {
                "aria-hidden": "true",
                focusable: "false"
              }
            ),
            " ",
            /* @__PURE__ */ jsx(
              "a",
              {
                href: `mailto:${displayEmail}`,
                "data-track-key": "contact_section_email_click",
                "data-track-name": "Kontaktbereich – E-posta",
                "data-track-location": "contact_section",
                "data-track-action": "email",
                children: displayEmail
              }
            )
          ] })
        ] })
      ] }),
      formToDisplay && /* @__PURE__ */ jsxs("form", { className: "contact-form", onSubmit: handleSubmit, children: [
        /* @__PURE__ */ jsx("div", { className: "form-grid", children: fields.map((f) => {
          const fieldConfig = fieldConfigMap[f.name] || {};
          const fieldId = fieldConfig.id || `contact-${String(f.name).replace(/[^a-z0-9_-]/gi, "-")}`;
          const fieldName = fieldConfig.name || f.name;
          const fieldType = fieldConfig.type || f.type || "text";
          const labelText = t(
            labelKeyMap[f.name] || f.label,
            fieldConfig.label || f.label
          );
          const errorId = `${fieldId}-error`;
          return /* @__PURE__ */ jsxs(
            "div",
            {
              className: `form-group ${fieldType === "textarea" ? "full-width" : ""}`,
              children: [
                /* @__PURE__ */ jsxs("label", { htmlFor: fieldId, children: [
                  labelText,
                  " ",
                  f.required && "*"
                ] }),
                fieldType === "textarea" ? /* @__PURE__ */ jsx(
                  "textarea",
                  {
                    id: fieldId,
                    name: fieldName,
                    rows: "5",
                    value: data[f.name] || "",
                    "aria-invalid": errors[f.name] ? "true" : "false",
                    "aria-describedby": errors[f.name] ? errorId : void 0,
                    onChange: (e) => safeSetField(
                      f.name,
                      e.target.value
                    ),
                    className: errors[f.name] ? "error" : ""
                  }
                ) : /* @__PURE__ */ jsx(
                  "input",
                  {
                    id: fieldId,
                    name: fieldName,
                    type: fieldType,
                    value: data[f.name] || "",
                    "aria-invalid": errors[f.name] ? "true" : "false",
                    "aria-describedby": errors[f.name] ? errorId : void 0,
                    onChange: (e) => safeSetField(
                      f.name,
                      e.target.value
                    ),
                    className: errors[f.name] ? "error" : ""
                  }
                ),
                errors[f.name] && /* @__PURE__ */ jsx(
                  "span",
                  {
                    id: errorId,
                    className: "error-message",
                    children: errors[f.name]
                  }
                )
              ]
            },
            f.name
          );
        }) }),
        /* @__PURE__ */ jsxs(
          "button",
          {
            type: "submit",
            className: `submit-button bg-button${trackContactButton ? " contact-form-submit-button" : ""}`,
            "data-track-key": trackContactButton ? "contact_form_submit" : void 0,
            "data-track-name": trackContactButton ? "Kontakt – Nachricht senden" : void 0,
            "data-track-location": trackContactButton ? "contact_page" : void 0,
            "data-track-action": trackContactButton ? "submit_contact_form" : void 0,
            "data-track-form": trackContactButton ? "contact_form" : void 0,
            disabled: isSubmitting,
            "aria-busy": isSubmitting,
            children: [
              isSubmitting && /* @__PURE__ */ jsx(
                "span",
                {
                  className: "loading-spinner",
                  "aria-hidden": "true"
                }
              ),
              /* @__PURE__ */ jsx("span", { children: isSubmitting ? t("contact.submitting") : t("contact.submit_label") })
            ]
          }
        ),
        errors._form && /* @__PURE__ */ jsx("span", { className: "error-message", children: errors._form })
      ] })
    ] }) })
  ] });
};
export {
  ContactSection as C
};
