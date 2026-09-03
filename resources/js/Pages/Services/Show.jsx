import React from "react";
import { Head, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import AppLayout from "@/Layouts/AppLayout";
import SafeHtml from "@/Components/Common/SafeHtml";
import FaqSection from "@/Components/Common/FaqSection";
import "../../../css/service-show.css";
import { buildResponsiveImage } from "@/utils/imageOptimizer";

import SeoHead from "@/Components/SeoHead";
const truncate = (text = "", max = 160) =>
    String(text || "")
        .replace(/<[^>]+>/g, "")
        .replace(/\s+/g, " ")
        .trim()
        .slice(0, max);

const normalizeLang = (lang) =>
    (String(lang || "")
        .toLowerCase()
        .split("-")[0]
        .trim() === "cz" ? "cs" : String(lang || "")
        .toLowerCase()
        .split("-")[0]
        .trim());

const unwrapResource = (value) => {
    if (!value) return null;
    if (value.data && typeof value.data === "object") return value.data;
    return value;
};

const getTranslations = (item) =>
    Array.isArray(item?.translations) ? item.translations : [];

const getActiveTranslation = (item, locale, fallbackLocale = "de") => {
    const translations = getTranslations(item);
    const current = normalizeLang(locale);
    const fallback = normalizeLang(fallbackLocale);

    return (
        translations.find(
            (tr) => normalizeLang(tr?.language_code) === current,
        ) ||
        translations.find(
            (tr) => normalizeLang(tr?.language_code) === fallback,
        ) ||
        translations.find((tr) => normalizeLang(tr?.language_code) === "de") ||
        translations[0] ||
        {}
    );
};

const getServiceTitle = (item, locale) => {
    const entity = unwrapResource(item);
    const tr = getActiveTranslation(
        entity,
        locale,
        entity?._meta?.default_language || "de",
    );

    const metaTitle = entity?.meta?.title || entity?.meta_title || "";

    return (
        tr?.name ||
        tr?.title ||
        entity?.name ||
        entity?.title ||
        entity?.category_name ||
        String(metaTitle).split("|")[0].trim() ||
        entity?.slug ||
        "Service"
    );
};

const getServiceDescription = (item, locale) => {
    const entity = unwrapResource(item);
    const tr = getActiveTranslation(
        entity,
        locale,
        entity?._meta?.default_language || "de",
    );

    return (
        tr?.content ||
        tr?.description ||
        tr?.body ||
        tr?.details ||
        entity?.content ||
        entity?.description ||
        entity?.long_description ||
        entity?.body ||
        entity?.details ||
        entity?.short_description ||
        entity?.meta?.description ||
        entity?.meta_description ||
        entity?.entity?.expertise ||
        entity?.entity?.about ||
        ""
    );
};

const getServiceShortDescription = (item, locale) => {
    const entity = unwrapResource(item);
    const tr = getActiveTranslation(
        entity,
        locale,
        entity?._meta?.default_language || "de",
    );

    return (
        tr?.short_description ||
        entity?.short_description ||
        truncate(getServiceDescription(entity, locale), 140)
    );
};

const getCategoryName = (item, locale) => {
    const entity = unwrapResource(item);
    const tr = getActiveTranslation(
        entity,
        locale,
        entity?._meta?.default_language || "de",
    );

    return tr?.name || entity?.name || entity?.title || "";
};

const pickText = (...values) => {
    for (const value of values) {
        if (typeof value === "string" && value.trim() !== "") {
            return value.trim();
        }
    }

    return "";
};

const pickMeta = (item, key) =>
    pickText(
        item?.meta?.[key],
        item?.meta?.[`meta_${key}`],
        item?.[`meta_${key}`],
        key === "title" ? item?.seo_title : undefined,
        key === "description" ? item?.seo_description : undefined,
        key === "keywords" ? item?.seo_keywords : undefined,
    );

const normalizeFaq = (faqSource, locale) => {
    const faq = Array.isArray(faqSource) ? { items: faqSource } : faqSource;
    if (!faq || typeof faq !== "object") {
        return { title: "", items: [] };
    }

    const faqTr = getActiveTranslation(
        faq,
        locale,
        faq?._meta?.default_language || "de",
    );
    const title = pickText(faqTr?.name, faq?.name, faq?.title, "FAQ");
    const rawItems = Array.isArray(faq.items)
        ? faq.items
        : Array.isArray(faq.questions)
          ? faq.questions
          : [];

    const items = rawItems
        .map((item, index) => {
            const itemTr = getActiveTranslation(
                item,
                locale,
                item?._meta?.default_language || "de",
            );

            return {
                id: item?.id ?? `${index}-${item?.question || "faq"}`,
                question: pickText(
                    itemTr?.question,
                    item?.question,
                    item?.title,
                ),
                answer: pickText(itemTr?.answer, item?.answer, item?.content),
            };
        })
        .filter((item) => item.question && item.answer);

    return { title, items };
};

export default function ServiceShow() {
    const { props } = usePage();

    const rawService = props.service ?? null;
    const rawCategory = props.category ?? null;

    const service = unwrapResource(rawService);
    const category = unwrapResource(rawCategory);

    const locale =
        props.locale ||
        service?._meta?.current_language ||
        service?._meta?.default_language ||
        "de";

    const appName = props.global?.appName || "Site";

    if (!service) {
        return (
            <section className="service-show__loading">
                <div className="service-show__spinner" />
                <p>Service not found.</p>
            </section>
        );
    }

    const activeTr = getActiveTranslation(
        service,
        locale,
        service?._meta?.default_language || "en",
    );
    const faq = normalizeFaq(
        props?.faq || service?.faq || service?.faqs,
        locale,
    );

    const title = getServiceTitle(service, locale);

    const description = getServiceDescription(service, locale);

    const shortDescription = getServiceShortDescription(service, locale);

    const categoryName = getCategoryName(category, locale);

    const image =
        service?.image ||
        "https://images.unsplash.com/photo-1581578731117-e0a820bd4928?q=80&w=1920&auto=format&fit=crop";
    const heroImage = buildResponsiveImage(image, {
        widths: [640, 960, 1280, 1600],
        width: 1600,
        quality: 72,
        transform: !image.includes("/media-cache/"),
    });

    const seoTitle =
        pickMeta(service, "title") ||
        activeTr?.meta_title ||
        `${title} - ${appName}`;

    const seoDescription =
        pickMeta(service, "description") ||
        activeTr?.meta_description ||
        shortDescription ||
        truncate(description);
    const seoKeywords =
        pickMeta(service, "keywords") || activeTr?.meta_keywords || "";

    const slug = service?.slug || "";
    const canonicalUrl = `/${locale}/${slug}`;
    const seoState = props?.seo || {};

    return (
        <AppLayout>
            <SeoHead
                title={seoTitle}
                description={seoDescription}
                keywords={seoKeywords}
                ogTitle={pickText(
                    service?.meta?.og_title,
                    service?.og_title,
                    seoTitle,
                )}
                ogDescription={pickText(
                    service?.meta?.og_description,
                    service?.og_description,
                    seoDescription,
                )}
                canonical={seoState.canonical || canonicalUrl}
                image={image}
                locale={locale}
                origin={props?.tenantSeo?.canonicalBaseUrl}
                alternates={seoState.alternates || []}
                xDefault={seoState.x_default}
                noindex={seoState.indexable === false}
            />

            <Head>
                <script type="application/ld+json">
                    {JSON.stringify({
                        "@context": "https://schema.org",
                        "@type": "Service",
                        ...(props?.tenantSeo?.canonicalUrl
                            ? {
                                  "@id": `${props.tenantSeo.canonicalUrl}#service`,
                                  url: props.tenantSeo.canonicalUrl,
                              }
                            : {}),
                        name: title,
                        description: truncate(description),
                        ...(faq.items.length
                            ? {
                                  subjectOf: {
                                      "@type": "FAQPage",
                                      mainEntity: faq.items.map((item) => ({
                                          "@type": "Question",
                                          name: item.question,
                                          acceptedAnswer: {
                                              "@type": "Answer",
                                              text: item.answer,
                                          },
                                      })),
                                  },
                              }
                            : {}),
                    })}
                </script>
            </Head>

            <Head>
                <link
                    rel="preload"
                    as="image"
                    href={heroImage.src}
                    imageSrcSet={heroImage.srcSet || undefined}
                    imageSizes="100vw"
                    fetchpriority="high"
                />
            </Head>

            <motion.section
                className="service-show__hero"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6 }}
            >
                <div className="service-show__hero-media">
                    <img
                        src={heroImage.src}
                        srcSet={heroImage.srcSet || undefined}
                        sizes="100vw"
                        width={1600}
                        height={700}
                        alt={title}
                        className="service-show__hero-img"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                    />
                    <div className="service-show__hero-overlay" />
                </div>

                <motion.div
                    className="service-show__hero-content"
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, delay: 0.2 }}
                >
                    {categoryName && (
                        <p className="service-show__badge">{categoryName}</p>
                    )}

                    <h1 className="service-show__title">{title}</h1>
                </motion.div>
            </motion.section>

            {description && (
                <motion.section
                    className="service-show__content fade-up"
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.7 }}
                >
                    <div className="service-show__content-inner service-show__content-grid">
                        <article className="service-show__prose">
                            <SafeHtml html={description} />
                        </article>
                    </div>
                </motion.section>
            )}

            <FaqSection title={faq.title} items={faq.items} variant="service" />

        </AppLayout>
    );
}
