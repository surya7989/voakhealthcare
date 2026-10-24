import React from 'react';
import { Calendar, Clock, ArrowRight } from 'lucide-react';
import { Skeleton } from '../../../shared/components/Skeleton';

export interface ArticleItem {
  id?: number | string;
  title: string;
  slug?: string;
  excerpt?: string;
  category?: string;
  reading_time_minutes?: number;
  published_at?: string;
  created_at?: string;
  author_name?: string;
}

export interface InsightsSectionProps {
  articles?: ArticleItem[];
  isLoading?: boolean;
}

const defaultArticles: ArticleItem[] = [
  {
    id: 1,
    title: 'Navigating Clinical Staffing Shortages in Regional Hospital Networks',
    slug: 'navigating-clinical-staffing-shortages-regional-hospitals',
    excerpt:
      'Strategic workforce deployment frameworks designed to stabilize acute care capacity without escalating operational overhead.',
    category: 'Workforce Strategy',
    reading_time_minutes: 5,
    published_at: '2026-03-15',
    author_name: 'Voak Advisory Group',
  },
  {
    id: 2,
    title: 'National Safety and Quality Health Service (NSQHS) Standards: 2026 Audit Readiness',
    slug: 'nsqhs-standards-2026-audit-readiness-framework',
    excerpt:
      'A practical compliance blueprint for clinical governance leaders preparing for accreditation and primary credentialing verification.',
    category: 'Clinical Governance',
    reading_time_minutes: 7,
    published_at: '2026-03-02',
    author_name: 'Clinical Quality Directorate',
  },
  {
    id: 3,
    title: 'Modernizing Residential Aged Care Operations: Roster Governance & Care Continuity',
    slug: 'modernizing-residential-aged-care-operations-roster-governance',
    excerpt:
      'How specialized allied health integration and reliable shift coverage safeguard patient dignity and regulatory compliance.',
    category: 'Aged Care Operations',
    reading_time_minutes: 4,
    published_at: '2026-02-20',
    author_name: 'Workforce Solutions Team',
  },
];

export const InsightsSection: React.FC<InsightsSectionProps> = ({
  articles = [],
  isLoading = false,
}) => {
  const displayArticles =
    articles && articles.length > 0 ? articles.slice(0, 3) : defaultArticles;

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return 'March 2026';
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-AU', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <section id="insights" className="py-16 lg:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-12">
          <div>
            <span className="text-xs font-extrabold tracking-wider text-voak-purple uppercase mb-2 block">
              Knowledge & Insights
            </span>
            <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-voak-navy">
              Healthcare Insights & Governance
            </h2>
            <p className="mt-2 text-base text-voak-muted max-w-xl">
              Strategic analysis, regulatory updates, and workforce frameworks from our healthcare operations team.
            </p>
          </div>

          <div className="mt-6 md:mt-0">
            <a
              href="#contact"
              className="inline-flex items-center gap-2 rounded-full border border-voak-border bg-white px-5 py-2.5 text-xs font-bold text-voak-navy shadow-xs hover:border-voak-purple hover:text-voak-purple transition-colors"
            >
              <span>View All Articles</span>
              <ArrowRight className="h-4 w-4" />
            </a>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {isLoading
            ? Array.from({ length: 3 }).map((_, idx) => (
                <div
                  key={`insight-skel-${idx}`}
                  className="rounded-3xl border border-voak-border bg-white p-6 h-72 flex flex-col justify-between"
                >
                  <div className="space-y-3">
                    <Skeleton className="h-5 w-24 rounded-full" />
                    <Skeleton className="h-7 w-full rounded" />
                    <Skeleton className="h-4 w-5/6 rounded" />
                  </div>
                  <div className="flex justify-between items-center pt-4 border-t border-voak-border">
                    <Skeleton className="h-4 w-20 rounded" />
                    <Skeleton className="h-4 w-16 rounded" />
                  </div>
                </div>
              ))
            : displayArticles.map((article) => (
                <article
                  key={article.id || article.title}
                  className="group relative flex flex-col justify-between rounded-3xl border border-voak-border/80 bg-white p-7 shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md hover:border-voak-purple/30"
                >
                  <div>
                    {/* Category pill */}
                    <div className="flex items-center justify-between mb-4">
                      <span className="inline-flex items-center rounded-full bg-voak-purple-light px-3 py-1 text-xs font-bold text-voak-purple">
                        {article.category || 'Clinical Advisory'}
                      </span>
                      <div className="flex items-center gap-1.5 text-xs text-voak-muted">
                        <Clock className="h-3.5 w-3.5" />
                        <span>{article.reading_time_minutes || 5} min read</span>
                      </div>
                    </div>

                    {/* Title */}
                    <h3 className="text-lg font-bold text-voak-navy group-hover:text-voak-purple transition-colors leading-snug mb-3">
                      <a href={`#insights`} className="focus-visible:outline-none focus-visible:underline">
                        {article.title}
                      </a>
                    </h3>

                    {/* Excerpt */}
                    <p className="text-xs sm:text-sm text-voak-muted leading-relaxed line-clamp-3">
                      {article.excerpt}
                    </p>
                  </div>

                  <div className="mt-6 pt-4 border-t border-voak-border/60 flex items-center justify-between">
                    <div className="flex items-center gap-2 text-xs text-voak-muted">
                      <Calendar className="h-3.5 w-3.5" />
                      <span>{formatDate(article.published_at || article.created_at)}</span>
                    </div>

                    <a
                      href={`#insights`}
                      aria-label={`Read article: ${article.title}`}
                      className="flex h-8 w-8 items-center justify-center rounded-full bg-voak-canvas text-voak-navy group-hover:bg-voak-purple group-hover:text-white transition-colors"
                    >
                      <ArrowRight className="h-4 w-4" />
                    </a>
                  </div>
                </article>
              ))}
        </div>
      </div>
    </section>
  );
};
