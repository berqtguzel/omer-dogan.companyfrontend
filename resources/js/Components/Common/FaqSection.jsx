import SafeHtml from "@/Components/Common/SafeHtml";
import "@/../css/faq-section.css";

const getItemKey = (item, index) =>
    String(item?.id || item?.question || item?.title || `faq-${index}`);

export default function FaqSection({
    items = [],
    variant = "default",
    className = "",
}) {
    const normalizedItems = Array.isArray(items)
        ? items.filter((item) => item?.question && item?.answer)
        : [];

    if (normalizedItems.length === 0) {
        return null;
    }

    return (
        <section
            className={`faq-section faq-section--${variant} ${className}`.trim()}
        >
            <div className="faq-section__inner container">
                <div className="faq-section__list">
                    {normalizedItems.map((item, index) => (
                        <details
                            key={getItemKey(item, index)}
                            className="faq-section__item"
                        >
                            <summary className="faq-section__question">
                                <span className="faq-section__number">
                                    {String(index + 1).padStart(2, "0")}
                                </span>
                                <span className="faq-section__question-text">
                                    {item.question}
                                </span>
                                <span
                                    className="faq-section__icon"
                                    aria-hidden="true"
                                />
                            </summary>

                            <div className="faq-section__answer">
                                <SafeHtml html={item.answer} />
                            </div>
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}
