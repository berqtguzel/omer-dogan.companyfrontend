import React from "react";
import { Link, usePage } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import AppLayout from "@/Layouts/AppLayout";
import SeoHead from "@/Components/SeoHead";
import {
    buildPageSeo,
    findTranslation,
    normalizeLocale,
    pickText,
} from "@/utils/pageSeo";
import { buildResponsiveImage } from "@/utils/imageOptimizer";
import { normalizeRichTextHeadings } from "@/utils/safeParse";
import "../../../css/blog.css";

const formatDate = (value, locale = "de") => {
    if (!value) return "";

    try {
        return new Intl.DateTimeFormat(locale, {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        }).format(new Date(value.replace(" ", "T")));
    } catch {
        return value;
    }
};

const localeBase = (locale = "de", location = "") => {
    try {
        const path = new URL(location).pathname;
        return path === `/${locale}/blog` || path.startsWith(`/${locale}/blog/`)
            ? `/${locale}`
            : locale === "de"
              ? ""
              : `/${locale}`;
    } catch {
        return locale === "de" ? "" : `/${locale}`;
    }
};

const pageLabel = (page, translation, key, fallback = "") =>
    pickText(
        translation?.[key],
        page?.[key],
        page?.labels?.[key],
        page?.ui?.[key],
        fallback,
    );

export default function BlogIndex() {
    const { t } = useTranslation();
    const {
        posts = [],
        categories = [],
        page,
        locale = "de",
        selectedCategory,
        currentUrl,
        tenantSeo,
    } = usePage().props;
    const base = localeBase(locale, currentUrl);
    const blogHref = `${base}/blog`;
    const normalizedLocale = normalizeLocale(locale);
    const pageTranslation = findTranslation(
        page?.translations,
        normalizedLocale,
    );
    const seo = buildPageSeo(page, locale, {
        title: "Blog",
        canonical: tenantSeo?.canonicalUrl,
    });
    const pageTitle = pickText(
        pageTranslation.name,
        page?.name,
        seo.title,
        "Blog",
    );
    const pageContent = pickText(pageTranslation.content, page?.content);
    const heroDescription = pickText(
        pageTranslation.description,
        page?.description,
        seo.description,
    );
    const eyebrow = pickText(
        pageTranslation.subtitle,
        page?.subtitle,
        page?.label,
        "",
    );
    const allLabel = pageLabel(page, pageTranslation, "all_label", t("corporateBlog.all"));
    const readLabel = pageLabel(
        page,
        pageTranslation,
        "read_more_label",
        t("corporateBlog.read"),
    );
    const emptyTitle = pageLabel(
        page,
        pageTranslation,
        "empty_title",
        t("corporateBlog.emptyTitle"),
    );
    const emptyText = pageLabel(
        page,
        pageTranslation,
        "empty_text",
        t("corporateBlog.emptyText"),
    );

    return (
        <AppLayout>
            <SeoHead
                title={seo.title}
                description={seo.description}
                keywords={seo.keywords}
                ogTitle={seo.ogTitle}
                ogDescription={seo.ogDescription}
                image={seo.image}
                canonical={tenantSeo?.canonicalUrl}
                locale={seo.locale}
                origin={tenantSeo?.canonicalBaseUrl}
            />

            <div className="blog-page">
                <section className="blog-hero">
                    <div className="blog-shell">
                        {eyebrow && <p className="blog-eyebrow">{eyebrow}</p>}
                        <h1 className="blog-title">{pageTitle}</h1>
                        {heroDescription && (
                            <p className="blog-subtitle">{heroDescription}</p>
                        )}

                        <div
                            className="blog-categories"
                            aria-label={t("corporateBlog.categories")}
                        >
                            {selectedCategory && (
                                <Link
                                    href={blogHref}
                                    className="blog-category-chip"
                                >
                                    {allLabel}
                                </Link>
                            )}
                            {categories.map((category) => (
                                <Link
                                    key={category.slug}
                                    href={`${blogHref}?category=${encodeURIComponent(category.slug)}`}
                                    className={`blog-category-chip ${
                                        selectedCategory === category.slug
                                            ? "is-active"
                                            : ""
                                    }`}
                                >
                                    {category.name}
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="blog-list-section">
                    <div className="blog-shell">
                        {pageContent && (
                            <div
                                className="blog-page-content"
                                dangerouslySetInnerHTML={{
                                    __html: normalizeRichTextHeadings(
                                        pageContent,
                                    ),
                                }}
                            />
                        )}

                        {posts.length === 0 ? (
                            <div className="blog-empty">
                                <h2>{emptyTitle}</h2>
                                <p>{emptyText}</p>
                            </div>
                        ) : (
                            <div
                                className={`blog-list blog-list--count-${Math.min(posts.length, 3)}`}
                            >
                                {posts.map((post) => {
                                    const cardImage = buildResponsiveImage(
                                        post.image,
                                        {
                                            widths: [320, 480, 640],
                                            width: 480,
                                            quality: 70,
                                            transform:
                                                !post.image?.includes(
                                                    "/media-cache/",
                                                ),
                                        },
                                    );

                                    return (
                                    <article
                                        key={post.id}
                                        className={`blog-card ${post.image ? "" : "blog-card--no-media"}`}
                                    >
                                        {post.image && (
                                            <Link
                                                href={`${base}/blog/${post.slug}`}
                                                className="blog-card__media"
                                            >
                                                <img
                                                    src={cardImage.src}
                                                    srcSet={
                                                        cardImage.srcSet ||
                                                        undefined
                                                    }
                                                    sizes="(max-width: 768px) 92vw, 380px"
                                                    alt={post.title}
                                                    width={480}
                                                    height={300}
                                                    loading="lazy"
                                                    decoding="async"
                                                />
                                            </Link>
                                        )}

                                        <div className="blog-card__body">
                                            <div className="blog-card__meta">
                                                <span>
                                                    {post.category?.name}
                                                </span>
                                                <span>
                                                    {formatDate(
                                                        post.published_at,
                                                        locale,
                                                    )}
                                                </span>
                                                {post.reading_time && (
                                                    <span>
                                                        {post.reading_time} min
                                                    </span>
                                                )}
                                            </div>

                                            <h2 className="blog-card__title">
                                                <Link
                                                    href={`${base}/blog/${post.slug}`}
                                                >
                                                    {post.title}
                                                </Link>
                                            </h2>

                                            <p className="blog-card__excerpt">
                                                {post.excerpt}
                                            </p>

                                            <div className="blog-card__tags">
                                                {(post.tags || [])
                                                    .slice(0, 4)
                                                    .map((tag) => (
                                                        <span key={tag}>
                                                            {tag}
                                                        </span>
                                                    ))}
                                            </div>

                                            <Link
                                                href={`${base}/blog/${post.slug}`}
                                                className="blog-card__link"
                                            >
                                                {readLabel}
                                            </Link>
                                        </div>
                                    </article>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
