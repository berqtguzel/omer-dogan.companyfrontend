import { useState } from 'react';
import SiteLink from './SiteLink';

export default function Brand({ site, footer = false }) {
    const source = footer ? site.footerLogo || site.logo : site.logo;
    const [failedSource, setFailedSource] = useState(null);
    const [loadedSource, setLoadedSource] = useState(null);
    const showImage = source && failedSource !== source;
    return (
        <SiteLink item={site.home} className="corporate-brand" aria-label={site.name}>
            {showImage && <img src={source} alt={site.name} width="220" height="64"
                className={footer && !site.footerLogo ? 'corporate-brand__plate' : undefined}
                data-loading={loadedSource !== source || undefined}
                loading={footer ? 'lazy' : 'eager'} decoding="async"
                onLoad={() => setLoadedSource(source)} onError={() => setFailedSource(source)} />}
            {(!showImage || loadedSource !== source) && <span className="corporate-brand__wordmark">{site.name}</span>}
        </SiteLink>
    );
}
