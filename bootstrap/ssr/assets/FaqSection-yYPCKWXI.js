import { jsx, jsxs } from "react/jsx-runtime";
import { a as SafeHtml } from "./AppLayout-CWzTYdDK.js";
const getItemKey = (item, index) => String((item == null ? void 0 : item.id) || (item == null ? void 0 : item.question) || (item == null ? void 0 : item.title) || `faq-${index}`);
function FaqSection({
  items = [],
  variant = "default",
  className = ""
}) {
  const normalizedItems = Array.isArray(items) ? items.filter((item) => (item == null ? void 0 : item.question) && (item == null ? void 0 : item.answer)) : [];
  if (normalizedItems.length === 0) {
    return null;
  }
  return /* @__PURE__ */ jsx(
    "section",
    {
      className: `faq-section faq-section--${variant} ${className}`.trim(),
      children: /* @__PURE__ */ jsx("div", { className: "faq-section__inner container", children: /* @__PURE__ */ jsx("div", { className: "faq-section__list", children: normalizedItems.map((item, index) => /* @__PURE__ */ jsxs(
        "details",
        {
          className: "faq-section__item",
          children: [
            /* @__PURE__ */ jsxs("summary", { className: "faq-section__question", children: [
              /* @__PURE__ */ jsx("span", { className: "faq-section__number", children: String(index + 1).padStart(2, "0") }),
              /* @__PURE__ */ jsx("span", { className: "faq-section__question-text", children: item.question }),
              /* @__PURE__ */ jsx(
                "span",
                {
                  className: "faq-section__icon",
                  "aria-hidden": "true"
                }
              )
            ] }),
            /* @__PURE__ */ jsx("div", { className: "faq-section__answer", children: /* @__PURE__ */ jsx(SafeHtml, { html: item.answer }) })
          ]
        },
        getItemKey(item, index)
      )) }) })
    }
  );
}
export {
  FaqSection as F
};
