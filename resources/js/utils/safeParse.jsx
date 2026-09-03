import parse, { domToReact, Element } from "html-react-parser";
import DOMPurify from "isomorphic-dompurify";

export const normalizeRichTextHeadings = (html = "") =>
    String(html)
        .replace(/<h1(\s[^>]*)?>/gi, "<h2$1>")
        .replace(/<\/h1>/gi, "</h2>");

export const normalizeInlineHtml = (html = "") =>
    normalizeRichTextHeadings(html).replace(
        /<\/?(?:h[1-6]|p|div)(?:\s[^>]*)?>/gi,
        "",
    );

export function safeParse(html, options) {
    const clean = DOMPurify.sanitize(normalizeRichTextHeadings(html || ""), {
        ALLOWED_TAGS: [
            "p",
            "strong",
            "em",
            "a",
            "ul",
            "ol",
            "li",
            "br",
            "h1",
            "h2",
            "h3",
            "h4",
            "h5",
            "h6",
            "blockquote",
            "img",
            "iframe",
            "div",
            "span",
            "small",
            "code",
            "figure",
            "figcaption",
        ],
        ALLOWED_ATTR: [
            "href",
            "title",
            "target",
            "rel",
            "src",
            "alt",
            "width",
            "height",
            "loading",
            "allow",
            "allowfullscreen",
            "class",
            "id",
        ],
    });

    const replace = (node) => {
        if (node instanceof Element && node.name === "a") {
            const props = node.attribs || {};
            const href = props.href || "";
            const isExternal = /^https?:\/\//i.test(href);

            if (isExternal) {
                return (
                    <a {...props} target="_blank" rel="noopener noreferrer">
                        {domToReact(node.children)}
                    </a>
                );
            }
        }

        if (
            node instanceof Element &&
            (node.name === "script" || node.name === "style")
        ) {
            return <></>;
        }

        return undefined;
    };

    return parse(clean, { replace, ...(options || {}) });
}
