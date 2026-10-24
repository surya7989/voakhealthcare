import React from 'react';
import { ArrowRight, Heart } from 'lucide-react';
import { Button } from '../../../shared/components/Button';

export interface HeroSectionProps {
  headline?: string;
  subheadline?: string;
  primaryCtaText?: string;
  primaryCtaUrl?: string;
  secondaryCtaText?: string;
  secondaryCtaUrl?: string;
}

export const HeroSection: React.FC<HeroSectionProps> = ({
  headline = 'Better Healthcare Together',
  subheadline = 'Voak Health Care partners with hospitals, clinics and healthcare organisations to deliver innovative, flexible and trusted healthcare solutions.',
  primaryCtaText = 'Explore Our Services',
  primaryCtaUrl = '#services',
  secondaryCtaText = 'Partner With Us',
  secondaryCtaUrl = '#contact',
}) => {
  return (
    <section id="hero" className="relative overflow-hidden pt-8 pb-16 lg:pt-14 lg:pb-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-8">
          {/* Left Column: Copy & Actions */}
          <div className="flex flex-col items-start lg:col-span-7">
            {/* Pill Badge */}
            <div className="inline-flex items-center gap-2 rounded-full border border-voak-purple/20 bg-voak-purple-light px-3.5 py-1 text-xs font-bold tracking-wider text-voak-purple uppercase mb-6 shadow-sm">
              <span className="h-2 w-2 rounded-full bg-voak-purple animate-pulse" aria-hidden="true" />
              <span>B2B Healthcare Services</span>
            </div>

            {/* Headline H1 */}
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-voak-navy leading-[1.12] mb-6">
              {headline}
            </h1>

            {/* Supporting Copy */}
            <p className="text-base sm:text-lg text-voak-muted max-w-2xl leading-relaxed mb-8">
              {subheadline}
            </p>

            {/* CTA Actions */}
            <div className="flex flex-wrap items-center gap-4">
              <a href={primaryCtaUrl}>
                <Button
                  variant="primary"
                  size="lg"
                  className="shadow-md hover:shadow-lg font-semibold gap-2"
                  rightIcon={<ArrowRight className="h-5 w-5" />}
                >
                  {primaryCtaText}
                </Button>
              </a>

              <a href={secondaryCtaUrl}>
                <Button
                  variant="pill-yellow"
                  size="lg"
                  className="font-bold border border-voak-yellow/40 hover:shadow-md"
                >
                  {secondaryCtaText}
                </Button>
              </a>
            </div>

            {/* Assurance footnote */}
            <div className="mt-8 flex items-center gap-3 text-xs text-voak-muted">
              <div className="h-2 w-2 rounded-full bg-voak-mint" aria-hidden="true" />
              <span>Registered Healthcare Workforce & Operations Partner Across Australia</span>
            </div>
          </div>

          {/* Right Column: Visual Stage (Approved Reference Benchmark 2) */}
          <div className="relative flex justify-center lg:col-span-5">
            <div className="relative w-full max-w-md lg:max-w-none flex items-center justify-center">
              {/* Decorative Sparkle Star */}
              <div
                className="absolute -top-4 left-4 z-20 text-3xl text-amber-400 select-none drop-shadow-sm animate-pulse"
                aria-hidden="true"
              >
                ✦
              </div>

              {/* Decorative Organic Color Shapes */}
              <div
                className="absolute -top-6 -right-6 h-64 w-64 rounded-full bg-voak-pink/30 blur-2xl -z-10"
                aria-hidden="true"
              />
              <div
                className="absolute -bottom-8 -left-8 h-64 w-64 rounded-full bg-voak-yellow/30 blur-2xl -z-10"
                aria-hidden="true"
              />

              {/* Main Portrait Card with Curved Border */}
              <div className="relative z-10 overflow-hidden rounded-[36px] border-4 border-white bg-white shadow-xl max-w-[320px] sm:max-w-[340px]">
                <img
                  src="/images/hero_doctor.jpg"
                  alt="Senior Healthcare Director in modern clinic"
                  className="h-[380px] w-full object-cover object-center"
                  width={340}
                  height={380}
                  loading="eager"
                />
              </div>

              {/* Floating Heart Squircle Badge (Top-Left) */}
              <div
                className="absolute top-8 -left-4 sm:-left-6 z-20 flex h-14 w-14 items-center justify-center rounded-2xl border-2 border-white bg-[#EEAFD7] shadow-lg animate-bounce"
                style={{ animationDuration: '4s' }}
                aria-hidden="true"
              >
                <Heart className="h-7 w-7 text-white fill-white" />
              </div>

              {/* Floating Facility Card (Bottom-Right) */}
              <div className="absolute -bottom-6 -right-2 sm:-right-6 z-20 flex flex-col gap-2 rounded-2xl border border-voak-border/80 bg-white p-2.5 shadow-xl max-w-[190px]">
                <img
                  src="/images/hospital_building.jpg"
                  alt="Accredited Partner Healthcare Facility"
                  className="h-20 w-full rounded-xl object-cover"
                  width={175}
                  height={80}
                />
                <div className="flex items-center justify-between gap-1 px-1">
                  <span className="text-[11px] font-bold text-voak-navy leading-tight">
                    Trusted Healthcare Partner
                  </span>
                  <a
                    href="#about"
                    aria-label="Learn more about partner facilities"
                    className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-voak-purple-light text-voak-purple hover:bg-voak-purple hover:text-white transition-colors"
                  >
                    <ArrowRight className="h-3.5 w-3.5" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
