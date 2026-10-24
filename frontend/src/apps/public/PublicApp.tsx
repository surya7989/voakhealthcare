import React from 'react';
import { useRouter } from '../../shared/router/Router';
import { HomePage } from './pages/HomePage';
import { AboutPage } from './pages/AboutPage';
import { WhyChooseVoakPage } from './pages/WhyChooseVoakPage';
import { HowWeWorkPage } from './pages/HowWeWorkPage';
import { QualityCompliancePage } from './pages/QualityCompliancePage';
import { ServicesPage } from './pages/ServicesPage';
import { ServiceDetailPage } from './pages/ServiceDetailPage';
import { IndustriesPage } from './pages/IndustriesPage';
import { IndustryDetailPage } from './pages/IndustryDetailPage';
import { NotFoundPage } from './pages/NotFoundPage';

export const PublicApp: React.FC = () => {
  const { path } = useRouter();
  const pathname = path.split('?')[0].split('#')[0];

  if (pathname === '/about') {
    return <AboutPage />;
  }

  if (pathname === '/why-choose-voak') {
    return <WhyChooseVoakPage />;
  }

  if (pathname === '/how-we-work') {
    return <HowWeWorkPage />;
  }

  if (pathname === '/quality-compliance') {
    return <QualityCompliancePage />;
  }

  if (pathname === '/services') {
    return <ServicesPage />;
  }

  if (pathname.startsWith('/services/')) {
    const slug = pathname.replace('/services/', '');
    return <ServiceDetailPage slug={slug} />;
  }

  if (pathname === '/industries') {
    return <IndustriesPage />;
  }

  if (pathname.startsWith('/industries/')) {
    const slug = pathname.replace('/industries/', '');
    return <IndustryDetailPage slug={slug} />;
  }

  if (pathname === '/' || pathname === '') {
    return <HomePage />;
  }

  return <NotFoundPage />;
};

export default PublicApp;
