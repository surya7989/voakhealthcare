import React from 'react';
import { useRouter } from '../../shared/router/Router';
import { HomePage } from './pages/HomePage';
import { AboutPage } from './pages/AboutPage';
import { WhyChooseVoakPage } from './pages/WhyChooseVoakPage';
import { HowWeWorkPage } from './pages/HowWeWorkPage';
import { QualityCompliancePage } from './pages/QualityCompliancePage';

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

  return <HomePage />;
};

export default PublicApp;
