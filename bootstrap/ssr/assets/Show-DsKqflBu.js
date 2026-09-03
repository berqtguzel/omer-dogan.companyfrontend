import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { usePage, Link } from "@inertiajs/react";
import { A as AppLayout, S as SeoHead } from "./AppLayout-CWzTYdDK.js";
/* empty css                    */
import "react-icons/fa";
import "react-icons/fa6";
import "react-i18next";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
const STATUS_MESSAGES = {
  403: {
    title: "Zugriff verweigert",
    message: "Sie haben keine Berechtigung, diese Seite aufzurufen."
  },
  404: {
    title: "Seite nicht gefunden",
    message: "Die angeforderte Seite konnte nicht gefunden werden."
  },
  500: {
    title: "Serverfehler",
    message: "Es ist ein unerwarteter Fehler aufgetreten."
  },
  503: {
    title: "Service nicht verfügbar",
    message: "Der Service ist momentan nicht verfügbar. Bitte versuchen Sie es später erneut."
  }
};
const cleanText = (value) => {
  if (value === null || value === void 0) return "";
  if (typeof value === "object") return "";
  return String(value).replace(/<[^>]*>/g, "").trim();
};
function ErrorShow() {
  var _a, _b, _c;
  const { props } = usePage();
  const status = Number(props.status || ((_a = props.error) == null ? void 0 : _a.status) || 500);
  const fallback = STATUS_MESSAGES[status] || STATUS_MESSAGES[500];
  const title = cleanText(props.title || ((_b = props.error) == null ? void 0 : _b.title)) || fallback.title;
  const message = cleanText(props.message || ((_c = props.error) == null ? void 0 : _c.message) || props.error) || fallback.message;
  const locale = props.locale || "de";
  return /* @__PURE__ */ jsxs(AppLayout, { children: [
    /* @__PURE__ */ jsx(SeoHead, { title: `${status} - ${title}`, description: message, noindex: true }),
    /* @__PURE__ */ jsx("section", { className: "error-page", children: /* @__PURE__ */ jsxs("div", { className: "error-container", children: [
      /* @__PURE__ */ jsx("h1", { className: "error-status", children: status }),
      /* @__PURE__ */ jsx("h2", { className: "error-title", children: title }),
      /* @__PURE__ */ jsx("p", { className: "error-message", children: message }),
      /* @__PURE__ */ jsxs("div", { className: "error-actions", children: [
        /* @__PURE__ */ jsx(Link, { href: `/${locale}`, className: "error-btn primary", children: "Zur Startseite" }),
        /* @__PURE__ */ jsx(
          Link,
          {
            href: `/${locale}/kontakt`,
            className: "error-btn secondary",
            children: "Kontakt aufnehmen"
          }
        )
      ] })
    ] }) })
  ] });
}
export {
  ErrorShow as default
};
