import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { useTranslation } from "react-i18next";
import { A as AppLayout, S as SeoHead } from "./AppLayout-CWzTYdDK.js";
import { C as ContactSection } from "./ContactSection-BRQuvdQ_.js";
import "@inertiajs/react";
import "react-icons/fa";
import "react-icons/fa6";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
function ContactIndex({ currentRoute = "kontakt" }) {
  const { t } = useTranslation();
  return /* @__PURE__ */ jsxs(AppLayout, { currentRoute, children: [
    /* @__PURE__ */ jsx(SeoHead, { title: t("contact.title", "Kontakt") }),
    /* @__PURE__ */ jsxs("main", { className: "contactx-page-wrapper", children: [
      /* @__PURE__ */ jsx("section", { className: "contactx-intro contactx-page-intro", children: /* @__PURE__ */ jsxs("div", { className: "contactx-intro__inner", children: [
        /* @__PURE__ */ jsx("span", { className: "contactx-eyebrow", children: t("contact.eyebrow", "Direkter Kontakt") }),
        /* @__PURE__ */ jsx("h1", { className: "contactx-title", children: t("contact.title", "Kontakt") }),
        /* @__PURE__ */ jsx("p", { children: t(
          "contact.page_intro",
          "Erzählen Sie uns kurz, wobei wir Sie unterstützen dürfen. Wir melden uns persönlich bei Ihnen."
        ) })
      ] }) }),
      /* @__PURE__ */ jsx(ContactSection, { trackContactButton: true })
    ] })
  ] });
}
export {
  ContactIndex as default
};
