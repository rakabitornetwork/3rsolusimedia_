import { Head, usePage } from '@inertiajs/react';

export default function SeoHead() {
    const seo = usePage().props.seo || {};
    const title = seo.title || '';
    const description = seo.description || '';
    const canonical = seo.canonical || '';
    const image = seo.image || '';
    const company = seo.company || '';
    const robots = seo.robots || 'index, follow';
    const locale = seo.locale || 'id_ID';

    return (
        <Head title={title}>
            <meta head-key="description" name="description" content={description} />
            <meta head-key="robots" name="robots" content={robots} />
            {canonical ? <link head-key="canonical" rel="canonical" href={canonical} /> : null}
            <meta head-key="og:type" property="og:type" content="website" />
            <meta head-key="og:site_name" property="og:site_name" content={company} />
            <meta head-key="og:locale" property="og:locale" content={locale} />
            <meta head-key="og:title" property="og:title" content={title} />
            <meta head-key="og:description" property="og:description" content={description} />
            {canonical ? <meta head-key="og:url" property="og:url" content={canonical} /> : null}
            {image ? <meta head-key="og:image" property="og:image" content={image} /> : null}
            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
            <meta head-key="twitter:title" name="twitter:title" content={title} />
            <meta head-key="twitter:description" name="twitter:description" content={description} />
            {image ? <meta head-key="twitter:image" name="twitter:image" content={image} /> : null}
        </Head>
    );
}
