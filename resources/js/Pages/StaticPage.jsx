import React from "react";
import { Head, usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import ContactSection from "@/Components/Home/Contact/ContactSection";
import SeoHead from "@/Components/SeoHead";
import FaqSection from "@/Components/Common/FaqSection";
import "@/../css/static-page.css";
import { buildResponsiveImage } from "@/utils/imageOptimizer";
import { normalizeRichTextHeadings } from "@/utils/safeParse";

const normalizeLocale = (locale = "de") =>
    String(locale || "de")
        .split("-")[0]
        .toLowerCase();

const pickText = (...values) => {
    for (const value of values) {
        if (typeof value === "string" && value.trim() !== "") {
            return value.trim();
        }
    }

    return "";
};

const findTranslation = (translations, locale) => {
    if (!Array.isArray(translations)) return {};

    return (
        translations.find(
            (item) =>
                normalizeLocale(item?.language_code || "") ===
                normalizeLocale(locale),
        ) ||
        translations[0] ||
        {}
    );
};

export default function StaticPage() {
    const { page, slug, locale = "de", tenantSeo } = usePage().props;
    const normalized = normalizeLocale(locale);
    const tr = findTranslation(page?.translations, normalized);
    const seo = page?.seo || {};

    const title = pickText(tr.name, page?.name, "Seite");
    const content = pickText(tr.content, page?.content);
    const image = pickText(tr.image, page?.image, seo.image);
    const heroImage = buildResponsiveImage(image, {
        widths: [640, 960, 1280, 1600],
        width: 1600,
        quality: 72,
    });

    const metaTitle = pickText(
        tr.meta_title,
        seo.meta_title,
        page?.meta_title,
        title,
    );
    const metaDescription =
        pickText(
            tr.meta_description,
            seo.meta_description,
            page?.meta_description,
        ) || content.replace(/<[^>]+>/g, "").slice(0, 160);
    const metaKeywords = pickText(
        tr.meta_keywords,
        seo.meta_keywords,
        page?.meta_keywords,
    );
    const ogTitle = pickText(tr.og_title, seo.og_title, page?.og_title, metaTitle);
    const ogDescription = pickText(
        tr.og_description,
        seo.og_description,
        page?.og_description,
        metaDescription,
    );
    const canonical = tenantSeo?.canonicalUrl;

    const faq = page?.faq || null;
    let faqTitle = "";
    let faqItems = [];

    if (faq) {
        const faqTr = findTranslation(faq.translations, normalized);
        faqTitle = pickText(faqTr?.name, faq?.name, "FAQ");

        if (Array.isArray(faq.items)) {
            faqItems = faq.items.map((item) => {
                const itemTr = findTranslation(item.translations, normalized);

                return {
                    id: item.id,
                    question: pickText(itemTr?.question, item?.question),
                    answer: pickText(itemTr?.answer, item?.answer),
                };
            });
        }
    }

    return (
        <AppLayout>
            <SeoHead
                title={metaTitle}
                description={metaDescription}
                keywords={metaKeywords}
                ogTitle={ogTitle}
                ogDescription={ogDescription}
                image={image}
                canonical={canonical}
                locale={normalized}
                origin={tenantSeo?.canonicalBaseUrl}
            />

            {heroImage.src && (
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
            )}

            <section className={`sp-hero ${image ? "sp-hero--has-img" : ""}`}>
                {image && (
                    <img
                        src={heroImage.src}
                        srcSet={heroImage.srcSet || undefined}
                        sizes="100vw"
                        alt={title}
                        className="sp-hero__img"
                        width={1600}
                        height={450}
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                    />
                )}
                <div className="sp-hero__overlay" />
                <div className="sp-hero__inner container">
                    <h1 className="sp-title">{title}</h1>
                </div>
            </section>

            {content && (
                <section className="sp-content">
                    <div className="container">
                        <article className="sp-card">
                            <div
                                className="sp-card__body sp-prose"
                                dangerouslySetInnerHTML={{
                                    __html: normalizeRichTextHeadings(
                                        content.replace(
                                            /\r\n|\n|\r/g,
                                            "<br />",
                                        ),
                                    ),
                                }}
                            />
                        </article>
                    </div>
                </section>
            )}

            <FaqSection title={faqTitle} items={faqItems} variant="static" />

            <ContactSection />
        </AppLayout>
    );
}
