import React from 'react';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { SeoHead } from '../seo/SeoHead';
import { Button } from '../../../shared/components/Button';
import { Link } from '../../../shared/router/Router';
import { AlertCircle, Briefcase, Building2, Home } from 'lucide-react';

export interface NotFoundPageProps {
  title?: string;
  message?: string;
  resourceType?: 'service' | 'industry' | 'page' | 'job';
}

export const NotFoundPage: React.FC<NotFoundPageProps> = ({
  title = 'Page Not Found',
  message = 'The healthcare service, sector profile, or page you are looking for does not exist or may have been relocated.',
  resourceType = 'page',
}) => {
  return (
    <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
      <SeoHead
        title={`${title} | Voak Health Care`}
        description={message}
        canonicalUrl="https://voakhealthcare.com.au/404"
      />

      <PublicHeader />

      <main id="main-content" className="flex-1 flex items-center justify-center py-20 px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <div className="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-voak-pink/20 border border-voak-pink/30 mb-8 shadow-sm">
            <AlertCircle className="h-10 w-10 text-voak-navy" aria-hidden="true" />
          </div>

          <div className="inline-block rounded-full border border-voak-border bg-white px-3.5 py-1 text-xs font-bold uppercase tracking-widest text-voak-purple mb-4 shadow-sm">
            404 Error
          </div>

          <h1 className="text-3xl font-extrabold tracking-tight text-voak-navy sm:text-4xl lg:text-5xl font-serif">
            {title}
          </h1>

          <p className="mt-4 text-base sm:text-lg text-voak-muted max-w-lg mx-auto">
            {message}
          </p>

          <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
            <Link to="/">
              <Button variant="primary" size="md" leftIcon={<Home className="h-4 w-4" />}>
                Return to Homepage
              </Button>
            </Link>

            {resourceType === 'service' ? (
              <Link to="/services">
                <Button variant="outline" size="md" leftIcon={<Briefcase className="h-4 w-4" />}>
                  Browse All Services
                </Button>
              </Link>
            ) : resourceType === 'industry' ? (
              <Link to="/industries">
                <Button variant="outline" size="md" leftIcon={<Building2 className="h-4 w-4" />}>
                  Explore All Sectors
                </Button>
              </Link>
            ) : resourceType === 'job' ? (
              <Link to="/careers">
                <Button variant="outline" size="md" leftIcon={<Briefcase className="h-4 w-4" />}>
                  Explore All Vacancies
                </Button>
              </Link>
            ) : (
              <>
                <Link to="/services">
                  <Button variant="outline" size="md" leftIcon={<Briefcase className="h-4 w-4" />}>
                    Browse Services
                  </Button>
                </Link>
                <Link to="/industries">
                  <Button variant="outline" size="md" leftIcon={<Building2 className="h-4 w-4" />}>
                    Explore Sectors
                  </Button>
                </Link>
              </>
            )}
          </div>
        </div>
      </main>

      <PublicFooter />
    </div>
  );
};
