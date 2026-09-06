import React from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import SeoHead from "@/Components/SeoHead";
import SafeHtml from "@/Components/Common/SafeHtml";
import FaqSection from "@/Components/Common/FaqSection";
import "../../../css/blog.css";
import { buildResponsiveImage } from "@/utils/imageOptimizer";

const formatDateTime = (value, locale = "de") => {
    if (!value) return "";

    try {
        return new Intl.DateTimeFormat(locale, {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
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

export default function BlogShow() {
    const { post, relatedPosts = [], locale = "de", currentUrl, tenantSeo } = usePage().props;
    const base = localeBase(locale, currentUrl);
    const faq = post?.faq || { title: "", items: [] };
    const articleImage = buildResponsiveImage(post?.image, {
        widths: [480, 768, 1040],
        width: 1040,
        quality: 72,
        transform: !post?.image?.includes("/media-cache/"),
    });

    return (
        <AppLayout>
            <SeoHead
                title={post.meta?.title || post.title}
                description={post.meta?.description || post.excerpt}
                keywords={post.meta?.keywords}
                canonical={tenantSeo?.canonicalUrl}
                image={post.image}
                locale={locale}
                origin={tenantSeo?.canonicalBaseUrl}
            />

            {articleImage.src && (
                <Head>
                    <link
                        rel="preload"
                        as="image"
                        href={articleImage.src}
                        imageSrcSet={articleImage.srcSet || undefined}
                        imageSizes="(max-width: 900px) 92vw, 520px"
                        fetchpriority="high"
                    />
                </Head>
            )}

            <div className="blog-page blog-page--show">
                <section className="blog-article-hero">
                    <div className="blog-shell">
                        <div className="blog-article-hero__inner">
                            <div>
                                <Link href={`${base}/blog`} className="blog-back-link">
                                    Blog
                                </Link>

                                <div className="blog-badges">
                                    <span>{post.category?.name}</span>
                                    {post.primary_service?.name && (
                                        <span>{post.primary_service.name}</span>
                                    )}
                                    {post.city && <span>{post.city}</span>}
                                </div>

                                <h1 className="blog-article-title">{post.title}</h1>

                                {post.excerpt && (
                                    <p className="blog-article-excerpt">
                                        {post.excerpt}
                                    </p>
                                )}

                                <div className="blog-article-meta">
                                    <span>{formatDateTime(post.published_at, locale)}</span>
                                    {post.reading_time && (
                                        <span>{post.reading_time} min</span>
                                    )}
                                    <span>{post.slug}</span>
                                </div>
                            </div>

                            {post.image && (
                                <img
                                    className="blog-article-image"
                                    src={articleImage.src}
                                    srcSet={articleImage.srcSet || undefined}
                                    sizes="(max-width: 900px) 92vw, 520px"
                                    alt={post.title}
                                    width={520}
                                    height={340}
                                    loading="eager"
                                    fetchpriority="high"
                                    decoding="async"
                                />
                            )}
                        </div>
                    </div>
                </section>

                <section className="blog-article-section">
                    <div className="blog-shell">
                        <article className="blog-content blog-content--single">
                            <div className="blog-prose">
                                {post.content ? (
                                    <SafeHtml html={post.content} />
                                ) : (
                                    <p>Dieser Beitrag hat noch keinen Inhalt.</p>
                                )}
                            </div>
                        </article>
                    </div>
                </section>

                <FaqSection title={faq.title} items={faq.items || []} variant="static" />

                {relatedPosts.length > 0 && (
                    <section className="blog-related">
                        <div className="blog-shell">
                            <h2>Related posts</h2>
                            <div className="blog-related__grid">
                                {relatedPosts.map((item) => (
                                    <Link
                                        key={item.slug}
                                        href={`${base}/blog/${item.slug}`}
                                        className="blog-related__item"
                                    >
                                        <span>{item.category?.name}</span>
                                        <strong>{item.title}</strong>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
