import { Head, usePage } from '@inertiajs/react';

const isAbsoluteUrl = (url) => /^https?:\/\//i.test(url || '');

const getBrowserOrigin = () => {
  if (typeof window === 'undefined') {
    return '';
  }

  return window.location.origin;
};

const normalizeOrigin = (origin = '') => {
  if (!origin) {
    return getBrowserOrigin();
  }

  try {
    return new URL(origin).origin;
  } catch {
    return origin.replace(/\/$/, '');
  }
};

const normalizeUrl = (url, origin = '') => {
  if (!url) {
    return '';
  }

  if (isAbsoluteUrl(url)) {
    return url;
  }

  const safeOrigin = normalizeOrigin(origin);

  if (!safeOrigin) {
    return url;
  }

  return `${safeOrigin}${url.startsWith('/') ? url : `/${url}`}`;
};

export default function SeoHead({
  title,
  description,
  keywords,
  canonical,
  image,
  ogTitle,
  ogDescription,
  type = 'website',
  locale = 'de',
  alternates = [],
  xDefault,
  origin,
  noindex = false,
  favicon,
}) {
  const pageProps = usePage().props || {};
  noindex = noindex || pageProps.corporateReady === false;
  const canonicalBaseUrl = pageProps.tenantSeo?.canonicalBaseUrl || '';
  const backendCanonicalUrl = pageProps.tenantSeo?.canonicalUrl || '';
  // Canonical URLs may only originate from the trusted server-side tenant
  // resolver. Relative page/API values are used for alternates, never as a
  // fallback to window.location or the request Host header.
  const canonicalUrl = noindex ? '' : backendCanonicalUrl;
  const imageUrl = normalizeUrl(image, origin);
  const faviconUrl = normalizeUrl(favicon, origin);
  const tenantUrl = (url) => {
    if (!url || !canonicalBaseUrl) return '';

    try {
      const parsed = new URL(url, canonicalBaseUrl);
      let path = parsed.pathname.replace(/\/{2,}/g, '/').replace(/^\/public(?=\/|$)/i, '');
      path = path || '/';
      if (/^\/[a-z]{2}\/(?:home|homepage|startseite)\/?$/i.test(path)) {
        path = `/${path.split('/')[1].toLowerCase()}/`;
      }
      return `${canonicalBaseUrl}${path}`;
    } catch {
      return '';
    }
  };
  const xDefaultUrl = tenantUrl(xDefault || canonicalUrl);
  const socialTitle = ogTitle || title;
  const socialDescription = ogDescription || description;

  return (
    <Head title={title}>
      {description && (
        <meta
          head-key="description"
          name="description"
          content={description}
        />
      )}

      {keywords && (
        <meta
          head-key="keywords"
          name="keywords"
          content={keywords}
        />
      )}

      {canonicalUrl && (
        <link
          head-key="canonical"
          rel="canonical"
          href={canonicalUrl}
        />
      )}

      {noindex && (
        <meta
          head-key="robots"
          name="robots"
          content="noindex, follow"
        />
      )}

      {!noindex && (
        <meta head-key="robots" name="robots" content="index, follow" />
      )}

      {faviconUrl && (
        <link head-key="favicon" rel="icon" href={faviconUrl} />
      )}
      {faviconUrl && (
        <link
          head-key="shortcut-icon"
          rel="shortcut icon"
          href={faviconUrl}
        />
      )}
      {faviconUrl && (
        <link head-key="apple-touch-icon" rel="apple-touch-icon" href={faviconUrl} />
      )}

      {alternates.map((alternate) => {
        const href = tenantUrl(alternate.href);

        if (!alternate.code || !href) {
          return null;
        }

        return (
          <link
            key={alternate.code}
            head-key={`alternate-${alternate.code}`}
            rel="alternate"
            hreflang={alternate.code}
            href={href}
          />
        );
      })}

      {xDefaultUrl && (
        <link
          head-key="alternate-x-default"
          rel="alternate"
          hreflang="x-default"
          href={xDefaultUrl}
        />
      )}

      {socialTitle && (
        <meta
          head-key="og:title"
          property="og:title"
          content={socialTitle}
        />
      )}

      {socialDescription && (
        <meta
          head-key="og:description"
          property="og:description"
          content={socialDescription}
        />
      )}

      {canonicalUrl && (
        <meta
          head-key="og:url"
          property="og:url"
          content={canonicalUrl}
        />
      )}

      <meta
        head-key="og:type"
        property="og:type"
        content={type}
      />

      <meta
        head-key="og:locale"
        property="og:locale"
        content={locale}
      />

      {imageUrl && (
        <meta
          head-key="og:image"
          property="og:image"
          content={imageUrl}
        />
      )}

      <meta
        head-key="twitter:card"
        name="twitter:card"
        content={imageUrl ? 'summary_large_image' : 'summary'}
      />

      {socialTitle && (
        <meta
          head-key="twitter:title"
          name="twitter:title"
          content={socialTitle}
        />
      )}

      {socialDescription && (
        <meta
          head-key="twitter:description"
          name="twitter:description"
          content={socialDescription}
        />
      )}

      {imageUrl && (
        <meta
          head-key="twitter:image"
          name="twitter:image"
          content={imageUrl}
        />
      )}
    </Head>
  );
}
