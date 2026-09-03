import React, { memo, useState } from "react";
import { Link } from "@inertiajs/react";
import "../../../../css/ServiceCard.css";
import SafeHtml from "@/Components/Common/SafeHtml";
import { useTranslation } from "react-i18next";
import { buildResponsiveImage } from "@/utils/imageOptimizer";

const API_BASE = (import.meta.env.VITE_API_BASE_URL || "").replace(/\/+$/, "");
export function formatServiceTitleFromSlug(slug) {
    return String(slug || "")
        .replace(/[-_]+/g, " ")
        .replace(/\s+/g, " ")
        .trim()
        .split(" ")
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

function looksLikeSlugTitle(value = "") {
    const text = String(value || "").trim();

    if (!text) return false;

    return (
        text.includes("-") ||
        text.includes("_") ||
        (text === text.toLowerCase() && !text.includes(" "))
    );
}

function resolveServiceTitle(item = {}) {
    const categoryName =
        item.category_name ||
        item.category?.name ||
        item.service_name ||
        item.service?.name ||
        formatServiceTitleFromSlug(
            item.category_slug ||
                item.service_slug ||
                item.category?.slug ||
                item.service?.slug,
        );
    const rawTitle =
        item.title || item.name || item.page_title || item.meta_title || "";

    if (looksLikeSlugTitle(rawTitle)) {
        return formatServiceTitleFromSlug(rawTitle || item.slug);
    }

    return (
        rawTitle ||
        categoryName ||
        formatServiceTitleFromSlug(item.slug) ||
        "Service"
    );
}

function normalizeUrl(url) {
    const value = String(url || "").trim();

    if (!value || /^\d+$/.test(value)) return null;
    if (value.startsWith("http") || value.startsWith("/")) return value;
    if (!API_BASE) return `/${value.replace(/^\/+/, "")}`;

    return `${API_BASE}/${value.replace(/^\/+/, "")}`;
}

const ServiceCard = memo((props) => {
    const { image, slug } = props;
    const { t, i18n } = useTranslation();

    const lang = (i18n.language || "de").split("-")[0].toLowerCase();
    const localePrefix = `/${lang}`;

    const displayTitle = resolveServiceTitle(props);

    const finalImage = normalizeUrl(image);
    const isLocalMirror = Boolean(
        finalImage &&
            (finalImage.startsWith("/storage/") ||
                finalImage.startsWith("/media-cache/") ||
                finalImage.startsWith("/media-proxy/") ||
                finalImage.includes("/storage/media-cache/")),
    );
    const responsiveImage = buildResponsiveImage(finalImage, {
        widths: [320, 480, 640],
        width: 480,
        quality: 70,
        transform: !isLocalMirror,
    });
    const imageSrc = responsiveImage.src;

    const href = `${localePrefix}/${slug || ""}`.replace(/\/+/g, "/");
    const [imageFailed, setImageFailed] = useState(false);

    return (
        <Link href={href} className="service-card" aria-label={displayTitle}>
            <div className="service-card__image-wrapper">
                {finalImage && !imageFailed ? (
                    <img
                        src={imageSrc}
                        srcSet={responsiveImage.srcSet || undefined}
                        sizes="(max-width: 640px) 92vw, (max-width: 1200px) 45vw, 400px"
                        alt={displayTitle}
                        className="service-card__image"
                        width="400"
                        height="400"
                        loading="eager"
                        decoding="async"
                        fetchpriority="auto"
                        onError={() => setImageFailed(true)}
                    />
                ) : (
                    <div
                        className="service-card__image-placeholder"
                        aria-hidden="true"
                    />
                )}
            </div>

            <div className="service-card__content">
                <h3 className="service-card__title">
                    <SafeHtml html={displayTitle} inline />
                </h3>

                <span className="service-card__button">
                    <span>{t("services.card.button", "Details")}</span>
                    <svg className="service-card__arrow" viewBox="0 0 24 24">
                        <path
                            d="M5 12H19M19 12L12 5M19 12L12 19"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </span>
            </div>
        </Link>
    );
});

ServiceCard.displayName = "ServiceCard";
export default ServiceCard;
