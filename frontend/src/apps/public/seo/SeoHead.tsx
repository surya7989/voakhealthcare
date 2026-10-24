import React, { useEffect } from 'react';

export interface SeoHeadProps {
  title?: string;
  description?: string;
  canonicalUrl?: string;
  structuredData?: Record<string, unknown>;
}

export const SeoHead: React.FC<SeoHeadProps> = ({
  title = 'Voak Health Care | Enterprise B2B Healthcare Operations & Workforce Solutions',
  description = 'Voak Health Care partners with hospitals, clinics, and aged care providers across Australia to deliver compliant clinical staffing, healthcare facility management, and operational consulting.',
  canonicalUrl = 'https://voakhealthcare.com.au/',
  structuredData,
}) => {
  useEffect(() => {
    // 1. Update Document Title
    const originalTitle = document.title;
    document.title = title;

    // 2. Helper to set or create meta tag
    const setMetaTag = (attrName: string, attrVal: string, content: string) => {
      let meta = document.querySelector(`meta[${attrName}="${attrVal}"]`) as HTMLMetaElement | null;
      if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute(attrName, attrVal);
        document.head.appendChild(meta);
      }
      meta.content = content;
    };

    setMetaTag('name', 'description', description);
    setMetaTag('property', 'og:title', title);
    setMetaTag('property', 'og:description', description);
    setMetaTag('property', 'og:url', canonicalUrl);
    setMetaTag('property', 'og:type', 'website');
    setMetaTag('name', 'twitter:card', 'summary_large_image');
    setMetaTag('name', 'twitter:title', title);
    setMetaTag('name', 'twitter:description', description);

    // 3. Update Canonical Link
    let canonical = document.querySelector('link[rel="canonical"]') as HTMLLinkElement | null;
    if (!canonical) {
      canonical = document.createElement('link');
      canonical.rel = 'canonical';
      document.head.appendChild(canonical);
    }
    canonical.href = canonicalUrl;

    // 4. Inject JSON-LD Schema
    const schemaData = structuredData || {
      '@context': 'https://schema.org',
      '@type': 'MedicalOrganization',
      name: 'VOAK Health Care',
      url: canonicalUrl,
      logo: `${canonicalUrl}assets/logo.png`,
      description: 'Enterprise B2B Healthcare Operations, Clinical Workforce Solutions and Facility Management across Australia.',
      telephone: '+61-1800-VOAK-HC',
      contactPoint: {
        '@type': 'ContactPoint',
        telephone: '+61-1800-VOAK-HC',
        contactType: 'Customer Service',
        areaServed: 'AU',
        availableLanguage: 'English',
      },
      address: {
        '@type': 'PostalAddress',
        addressLocality: 'Sydney',
        addressRegion: 'NSW',
        addressCountry: 'AU',
      },
    };

    let script = document.getElementById('seo-json-ld') as HTMLScriptElement | null;
    if (!script) {
      script = document.createElement('script');
      script.id = 'seo-json-ld';
      script.type = 'application/ld+json';
      document.head.appendChild(script);
    }
    script.text = JSON.stringify(schemaData);

    return () => {
      document.title = originalTitle;
      const jsonLd = document.getElementById('seo-json-ld');
      if (jsonLd) jsonLd.remove();
    };
  }, [title, description, canonicalUrl, structuredData]);

  return null;
};
