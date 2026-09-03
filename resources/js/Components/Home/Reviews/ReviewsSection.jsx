import React, { memo, useMemo } from "react";
import { usePage } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import SafeHtml from "@/Components/Common/SafeHtml";
import { renderText } from "@/utils/renderValue";
import "../../../../css/ReviewsSection.css";

const MAX_ITEMS = 6;

/** "Ömer Doğan" → "ÖD" */
function initials(name) {
    return String(name || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
}

function formatDate(value, locale) {
    if (!value) return "";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return "";

    try {
        return new Intl.DateTimeFormat(locale, {
            year: "numeric",
            month: "long",
        }).format(date);
    } catch (e) {
        return date.toISOString().slice(0, 7);
    }
}

const Stars = ({ value, label }) => {
    const rounded = Math.round(Number(value) || 0);

    return (
        <span className="rev-stars" role="img" aria-label={label}>
            {[1, 2, 3, 4, 5].map((star) => (
                <svg
                    key={star}
                    className={`rev-stars__icon${star <= rounded ? " is-on" : ""}`}
                    viewBox="0 0 20 20"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path d="M10 1.6l2.47 5.28 5.53.76-4.03 3.83 1.02 5.6L10 14.4l-4.99 2.67 1.02-5.6L2 7.64l5.53-.76z" />
                </svg>
            ))}
        </span>
    );
};

const ReviewsSection = memo(({ content = {} }) => {
    const { t } = useTranslation();
    const { props } = usePage();
    const locale = props?.locale || "de";

    const reviews = props?.reviews || {};
    const items = useMemo(
        () => (Array.isArray(reviews.items) ? reviews.items.slice(0, MAX_ITEMS) : []),
        [reviews.items],
    );

    if (items.length === 0) {
        return null;
    }

    const average = reviews.summary?.average;
    const count = reviews.summary?.count ?? items.length;

    const title = renderText(
        content.reviews_title,
        t("reviews.title", "Das sagen unsere Kunden"),
    );
    const subtitle = renderText(
        content.reviews_subtitle,
        t(
            "reviews.subtitle",
            "Echte Rückmeldungen aus Hotels, Büros und Objekten, die wir täglich betreuen.",
        ),
    );

    return (
        <section id="reviews" className="rev-section">
            <div className="rev-container container">
                <header className="rev-head">
                    <div className="rev-head__copy">
                        <span className="rev-eyebrow">
                            {t("reviews.eyebrow", "Kundenstimmen")}
                            {reviews.is_demo && (
                                <em className="rev-demo">
                                    {t("common.demo", "Demo")}
                                </em>
                            )}
                        </span>
                        <h2 className="rev-title">
                            <SafeHtml html={title} inline />
                        </h2>
                        {subtitle && (
                            <p className="rev-subtitle">
                                <SafeHtml html={subtitle} inline />
                            </p>
                        )}
                    </div>

                    {average != null && (
                        <div className="rev-score">
                            <strong className="rev-score__value">
                                {average.toLocaleString(locale, {
                                    minimumFractionDigits: 1,
                                    maximumFractionDigits: 1,
                                })}
                            </strong>
                            <Stars
                                value={average}
                                label={t("reviews.rating_label", "Bewertung")}
                            />
                            <span className="rev-score__count">
                                {t("reviews.count", "{{count}} Bewertungen", {
                                    count,
                                })}
                            </span>
                        </div>
                    )}
                </header>

                <div className="rev-grid">
                    {items.map((item, index) => {
                        const name =
                            item.name || t("reviews.anonymous", "Kunde");
                        const date = formatDate(item.date, locale);
                        const meta = [item.role, date].filter(Boolean).join(" · ");

                        return (
                            <article
                                key={item.id ?? `review-${index}`}
                                className="rev-card"
                            >
                                {item.rating != null && (
                                    <Stars
                                        value={item.rating}
                                        label={`${item.rating} / 5`}
                                    />
                                )}

                                <blockquote className="rev-card__quote">
                                    <SafeHtml html={item.comment} />
                                </blockquote>

                                <footer className="rev-card__author">
                                    <span className="rev-card__avatar">
                                        {item.avatar ? (
                                            <img
                                                src={item.avatar}
                                                alt=""
                                                width={44}
                                                height={44}
                                                loading="lazy"
                                                decoding="async"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            initials(name)
                                        )}
                                    </span>
                                    <span className="rev-card__identity">
                                        <strong className="rev-card__name">
                                            {name}
                                        </strong>
                                        {meta && (
                                            <span className="rev-card__meta">
                                                {meta}
                                            </span>
                                        )}
                                    </span>
                                </footer>
                            </article>
                        );
                    })}
                </div>
            </div>
        </section>
    );
});

ReviewsSection.displayName = "ReviewsSection";

export default ReviewsSection;
