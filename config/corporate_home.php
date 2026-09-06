<?php

return [
    // Keep the previous tenant's content out of the corporate shell until the
    // new tenant mappings have been verified in all active locales.
    'content_ready' => filter_var(env('CORPORATE_CONTENT_READY', false), FILTER_VALIDATE_BOOLEAN),
    'preview_content' => filter_var(env('CORPORATE_PREVIEW_CONTENT', false), FILTER_VALIDATE_BOOLEAN),
    'site_name' => 'Ömer Dogan Company GmbH',
    'locales' => ['de', 'en', 'tr'],
    // Exact panel page slugs, never inferred from cleaning service names.
    // Populate company/project selections only after confirming their source.
    'group_page' => 'unternehmensgruppe',
    'career_page' => 'karriere',
    'static_pages' => [
        'unternehmensgruppe' => 'unternehmensgruppe',
        'ueber-uns' => 'ueber-uns',
        'karriere' => 'karriere',
        'impressum' => 'impressum',
        'datenschutz' => 'datenschutz',
    ],
    'business_area_pages' => [
        'hospitality' => 'hospitality',
        'real-estate' => 'immobilien-projekte',
        'facility' => 'facility-industry',
        'trade-technology' => 'handel-technologie',
    ],
    'company_pages' => [],
    'project_pages' => [],
];
