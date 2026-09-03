import React, {
    useMemo,
    memo,
} from "react";
import { useTranslation } from "react-i18next";
import { usePage } from "@inertiajs/react";
import SafeHtml from "@/Components/Common/SafeHtml";
import { buildResponsiveImage } from "@/utils/imageOptimizer";
import "../../../../css/ServiceCategories.css";

export const SERVICE_IMAGE_SIZES = "(max-width: 768px) 92vw, 367px";

const API_BASE = (import.meta.env.VITE_API_BASE_URL || "").replace(/\/+$/, "");

export const buildServiceImage = (rawImage, baseUrl = API_BASE) => {
    const normalized = String(rawImage || "").trim();

    if (!normalized || /^\d+$/.test(normalized)) {
        return {
            src: "",
            srcSet: "",
        };
    }

    const isLocalMediaCache = /^\/?media-cache\//i.test(normalized);
    const absoluteUrl = normalized.startsWith("http")
        ? normalized
        : isLocalMediaCache
          ? `/${normalized.replace(/^\/+/, "")}`
          : `${baseUrl}/${normalized.replace(/^\/+/, "")}`;

    if (/\/media-cache\/.+\.(avif|webp|png|jpe?g|gif|svg)(\?.*)?$/i.test(absoluteUrl)) {
        return {
            src: absoluteUrl,
            srcSet: "",
        };
    }

    return buildResponsiveImage(absoluteUrl, {
        widths: [360, 480, 640, 720],
        width: 480,
        quality: 65,
    });
};

const firstImageValue = (...values) => {
    for (const value of values) {
        if (Array.isArray(value)) {
            const nested = firstImageValue(...value);
            if (nested) return nested;
            continue;
        }

        if (value && typeof value === "object") {
            const nested = firstImageValue(
                value.url,
                value.path,
                value.src,
                value.original_url,
                value.id,
            );
            if (nested) return nested;
            continue;
        }

        if (typeof value === "number") {
            return String(value);
        }

        if (typeof value === "string" && value.trim()) {
            return value.trim();
        }
    }

    return "";
};

const ServiceCategories = memo(({ content = {} }) => {
    const { t } = useTranslation();
    const { props } = usePage();
    const locale = props?.locale || "de";

    const rawHighlights =
        props.widgets?.highlights ??
        props.widgets?.service_highlights ??
        props.global?.widgets?.highlights ??
        props.global?.widgets?.service_highlights ??
        [];
    const list = Array.isArray(rawHighlights?.data)
        ? rawHighlights.data
        : Array.isArray(rawHighlights)
          ? rawHighlights
          : [];
    const services = useMemo(
        () =>
            list.map((s) => {
                const tr = (s.translations || []).find(
                    (i) =>
                        String(i.language_code || "").toLowerCase() ===
                        String(locale).toLowerCase(),
                );

                const rawImage = firstImageValue(
                    s.image_url,
                    s.image,
                    s.media,
                    s.thumbnail,
                    s.featured_image,
                    s.cover_image,
                );
                const image = buildServiceImage(rawImage);
                return {
                    ...s,
                    name: tr?.name || s.name,
                    description: tr?.description || s.description,
                    image: image.src,
                    imageSrcSet: image.srcSet,
                };
            }),
        [list, locale],
    );

    return (
        <section className="svc-section">
            <div className="svc-container">
                <h2 className="svc-title">
                    <SafeHtml
                        html={
                            content.section_services ||
                            t("services.section_title")
                        }
                    />
                </h2>

                <div className="svc-grid">
                    {services.map((svc, index) => {
                        return (
                        <div
                            key={svc.id}
                            className="svc-card"
                        >
                            <div className="svc-card-img-wrap">
                                {svc.image ? <img
                                    src={svc.image}
                                    srcSet={svc.imageSrcSet || undefined}
                                    sizes={SERVICE_IMAGE_SIZES}
                                    className="svc-card-img"
                                    decoding="async"
                                    loading="eager"
                                    fetchpriority={index === 0 ? "high" : "auto"}
                                    width={360}
                                    height={230}
                                    alt={
                                        svc.image_alt ||
                                        svc.name ||
                                        "Cleaning service"
                                    }
                                    onError={(e) => {
                                        e.currentTarget.style.visibility = "hidden";
                                    }}
                                /> : <div className="svc-card-img svc-card-img--placeholder" aria-hidden="true" />}
                            </div>

                            <div className="svc-card-body">
                                <h3 className="svc-card-title">{svc.name}</h3>
                                <p className="svc-card-desc">
                                    <SafeHtml html={svc.description} />
                                </p>
                            </div>
                        </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
});

ServiceCategories.displayName = "ServiceCategories";

export default ServiceCategories;
