import React, { memo, useState } from "react";
import { usePage } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import SafeHtml from "@/Components/Common/SafeHtml";
import { renderText } from "@/utils/renderValue";
import "../../../../css/HomeFaqSection.css";

const HomeFaqSection = memo(({ content = {} }) => {
    const { t } = useTranslation();
    const { props } = usePage();

    const faq = props?.faq || {};
    const items = Array.isArray(faq.items) ? faq.items : [];

    // ilk soru açık başlar; tıklananı aç/kapa
    const [openId, setOpenId] = useState(() => items[0]?.id ?? 0);

    if (items.length === 0) {
        return null;
    }

    const title = renderText(
        content.faq_title,
        t("faq.title", "Häufige Fragen"),
    );
    const subtitle = renderText(
        content.faq_subtitle,
        t(
            "faq.subtitle",
            "Die Antworten, nach denen am häufigsten gefragt wird. Ihre Frage ist nicht dabei? Melden Sie sich einfach.",
        ),
    );

    return (
        <section id="faq" className="hfaq-section">
            <div className="hfaq-container container">
                <header className="hfaq-head">
                    <span className="hfaq-eyebrow">
                        {t("faq.eyebrow", "FAQ")}
                        {faq.is_demo && (
                            <em className="hfaq-demo">
                                {t("common.demo", "Demo")}
                            </em>
                        )}
                    </span>
                    <h2 className="hfaq-title">
                        <SafeHtml html={title} inline />
                    </h2>
                    {subtitle && (
                        <p className="hfaq-subtitle">
                            <SafeHtml html={subtitle} inline />
                        </p>
                    )}
                </header>

                <div className="hfaq-list">
                    {items.map((item, index) => {
                        const key = item.id ?? index;
                        const isOpen = openId === key;

                        return (
                            <div
                                key={key}
                                className={`hfaq-item${isOpen ? " is-open" : ""}`}
                            >
                                <h3 className="hfaq-item__heading">
                                    <button
                                        type="button"
                                        className="hfaq-item__trigger"
                                        aria-expanded={isOpen}
                                        aria-controls={`hfaq-panel-${key}`}
                                        id={`hfaq-trigger-${key}`}
                                        onClick={() =>
                                            setOpenId(isOpen ? null : key)
                                        }
                                    >
                                        <span className="hfaq-item__question">
                                            {item.question}
                                        </span>
                                        <span
                                            className="hfaq-item__icon"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </h3>

                                <div
                                    className="hfaq-item__panel"
                                    id={`hfaq-panel-${key}`}
                                    role="region"
                                    aria-labelledby={`hfaq-trigger-${key}`}
                                    hidden={!isOpen}
                                >
                                    <div className="hfaq-item__answer">
                                        <SafeHtml html={item.answer} />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
});

HomeFaqSection.displayName = "HomeFaqSection";

export default HomeFaqSection;
