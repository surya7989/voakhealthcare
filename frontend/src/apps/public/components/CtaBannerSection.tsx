import React from 'react';
import { ArrowRight, Calendar, HeartHandshake } from 'lucide-react';
import { Button } from '../../../shared/components/Button';

export interface CtaBannerSectionProps {
  title?: string;
  description?: string;
  primaryCtaText?: string;
  primaryCtaUrl?: string;
  secondaryCtaText?: string;
  secondaryCtaUrl?: string;
}

export const CtaBannerSection: React.FC<CtaBannerSectionProps> = ({
  title = 'Transform Your Healthcare Operations Today',
  description = 'Partner with Voak Health Care to secure governed workforce reliability, credentialed clinical talent, and operational continuity across your facilities.',
  primaryCtaText = 'Request Formal Proposal',
  primaryCtaUrl = '#contact',
  secondaryCtaText = 'Schedule Discovery Call',
  secondaryCtaUrl = '#contact',
}) => {
  return (
    <section className="py-12 sm:py-16">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="relative overflow-hidden rounded-[36px] bg-gradient-to-br from-[#665CE9] via-[#4F46E5] to-[#252B5E] px-6 py-14 sm:px-12 sm:py-18 lg:px-16 text-center text-white shadow-xl">
          {/* Subtle background glow ornaments */}
          <div
            className="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-white/10 blur-3xl pointer-events-none"
            aria-hidden="true"
          />
          <div
            className="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-[#EEAFD7]/20 blur-3xl pointer-events-none"
            aria-hidden="true"
          />

          <div className="relative z-10 max-w-3xl mx-auto">
            {/* Pill */}
            <div className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 backdrop-blur-xs px-4 py-1 text-xs font-bold tracking-wider uppercase mb-6 text-white/90">
              <HeartHandshake className="h-3.5 w-3.5 text-[#FBDC62]" />
              <span>Institutional Healthcare Partnerships</span>
            </div>

            {/* Title */}
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white mb-6 leading-tight">
              {title}
            </h2>

            {/* Description */}
            <p className="text-base sm:text-lg text-white/85 leading-relaxed mb-10 max-w-2xl mx-auto">
              {description}
            </p>

            {/* Action Buttons */}
            <div className="flex flex-wrap items-center justify-center gap-4">
              <a href={primaryCtaUrl}>
                <Button
                  variant="pill-yellow"
                  size="lg"
                  className="font-bold shadow-lg hover:shadow-xl text-voak-navy px-8"
                  rightIcon={<ArrowRight className="h-5 w-5" />}
                >
                  {primaryCtaText}
                </Button>
              </a>

              <a href={secondaryCtaUrl}>
                <Button
                  variant="outline"
                  size="lg"
                  className="border-white/50 text-white hover:bg-white/15 hover:border-white font-semibold gap-2"
                  leftIcon={<Calendar className="h-4 w-4" />}
                >
                  {secondaryCtaText}
                </Button>
              </a>
            </div>

            {/* SLA Guarantee note */}
            <p className="mt-8 text-xs text-white/60">
              Strict Confidentiality &bull; Non-Disclosure Agreements Honored &bull; Australian Clinical Governance Standards
            </p>
          </div>
        </div>
      </div>
    </section>
  );
};
