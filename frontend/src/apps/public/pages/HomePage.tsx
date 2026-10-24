import React, { useEffect, useState } from 'react';
import { SeoHead } from '../seo/SeoHead';
import { PublicHeader } from '../components/PublicHeader';
import { HeroSection } from '../components/HeroSection';
import { FeatureCardsSection } from '../components/FeatureCardsSection';
import { ServicesBentoSection, type ServiceItem } from '../components/ServicesBentoSection';
import { TrustSection, type MetricItem } from '../components/TrustSection';
import { IndustriesSection, type IndustryItem } from '../components/IndustriesSection';
import { WorkflowSection } from '../components/WorkflowSection';
import { InsightsSection, type ArticleItem } from '../components/InsightsSection';
import { CtaBannerSection } from '../components/CtaBannerSection';
import { PublicFooter } from '../components/PublicFooter';
import { apiClient } from '../../../shared/api/apiClient';

interface HomepageConfig {
  hero_headline?: string;
  hero_subheadline?: string;
  hero_primary_cta_text?: string;
  hero_primary_cta_url?: string;
  hero_secondary_cta_text?: string;
  hero_secondary_cta_url?: string;
  proof_metrics?: MetricItem[];
}

export const HomePage: React.FC = () => {
  const [config, setConfig] = useState<HomepageConfig | null>(null);
  const [services, setServices] = useState<ServiceItem[]>([]);
  const [industries, setIndustries] = useState<IndustryItem[]>([]);
  const [articles, setArticles] = useState<ArticleItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;

    async function loadHomepageData() {
      setIsLoading(true);

      const results = await Promise.allSettled([
        apiClient.get<{ config: HomepageConfig }>('/public/homepage'),
        apiClient.get<{ services: ServiceItem[] }>('/public/services'),
        apiClient.get<{ industries: IndustryItem[] }>('/public/industries'),
        apiClient.get<{ articles: ArticleItem[] }>('/public/articles'),
      ]);

      if (!isMounted) return;

      // 1. Homepage Config
      if (results[0].status === 'fulfilled' && results[0].value?.data?.config) {
        setConfig(results[0].value.data.config);
      }

      // 2. Services
      if (results[1].status === 'fulfilled' && results[1].value?.data?.services) {
        setServices(results[1].value.data.services);
      }

      // 3. Industries
      if (results[2].status === 'fulfilled' && results[2].value?.data?.industries) {
        setIndustries(results[2].value.data.industries);
      }

      // 4. Articles
      if (results[3].status === 'fulfilled' && results[3].value?.data?.articles) {
        setArticles(results[3].value.data.articles);
      }

      setIsLoading(false);
    }

    loadHomepageData();

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <div className="min-h-screen bg-voak-canvas text-voak-navy flex flex-col font-sans selection:bg-voak-purple selection:text-white">
      {/* Dynamic SEO Meta & Schema */}
      <SeoHead
        title="Voak Health Care | Enterprise B2B Healthcare Operations & Workforce Solutions"
        description="Voak Health Care partners with acute hospitals, regional health services, and private healthcare networks across Australia to deliver compliant clinical workforce solutions, operations BPO, and governance."
        canonicalUrl="https://voakhealthcare.com.au/"
      />

      {/* Accessible Skip Navigation */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-xl focus:bg-voak-purple focus:px-4 focus:py-2 focus:text-white focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-white"
      >
        Skip to main content
      </a>

      {/* Corporate Sticky Header */}
      <PublicHeader />

      {/* Semantic Main Content Landmark */}
      <main id="main-content" className="flex-1">
        {/* Section 01: Hero (Image 2 Parity) */}
        <HeroSection
          headline={config?.hero_headline || 'Better Healthcare Together'}
          subheadline={
            config?.hero_subheadline ||
            'Voak Health Care partners with hospitals, clinics and healthcare organisations to deliver innovative, flexible and trusted healthcare solutions.'
          }
          primaryCtaText={config?.hero_primary_cta_text || 'Explore Our Services'}
          primaryCtaUrl={config?.hero_primary_cta_url || '#services'}
          secondaryCtaText={config?.hero_secondary_cta_text || 'Partner With Us'}
          secondaryCtaUrl={config?.hero_secondary_cta_url || '#contact'}
        />

        {/* Section 02: 3 Pastel Feature Cards */}
        <FeatureCardsSection />

        {/* Section 03: Asymmetric Services Bento Grid */}
        <ServicesBentoSection services={services} isLoading={isLoading} />

        {/* Section 04: "Why Voak" Trust Section & Operational Metrics */}
        <TrustSection metrics={config?.proof_metrics} />

        {/* Section 05: Industries We Serve */}
        <IndustriesSection industries={industries} isLoading={isLoading} />

        {/* Section 06: How We Work (4-Stage Operational Model) */}
        <WorkflowSection />

        {/* Section 07: Insights & Governance Articles */}
        <InsightsSection articles={articles} isLoading={isLoading} />

        {/* Section 08: Executive CTA Conversion Banner */}
        <CtaBannerSection />
      </main>

      {/* Enterprise 5-Column Footer */}
      <PublicFooter />
    </div>
  );
};
export default HomePage;
