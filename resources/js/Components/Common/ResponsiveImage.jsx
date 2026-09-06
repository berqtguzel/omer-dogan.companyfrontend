import { useState } from 'react';
import { buildResponsiveImage } from '@/utils/imageOptimizer';

export default function ResponsiveImage({ src, alt, sizes = '(min-width: 60rem) 33vw, 100vw', priority = false, className = '', children }) {
    const [failed, setFailed] = useState(null);
    const image = buildResponsiveImage(src, { widths: [480, 800, 1200, 1600], width: priority ? 1600 : 800, quality: 75 });
    return <div className={`group-media ${className}`}>
        {image.src && failed !== src
            ? <img src={image.src} srcSet={image.srcSet || undefined} sizes={sizes} alt={alt}
                width="1200" height="900" loading={priority ? 'eager' : 'lazy'} fetchpriority={priority ? 'high' : 'auto'}
                decoding="async" onError={() => setFailed(src)} />
            : children}
    </div>;
}
