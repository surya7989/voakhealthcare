import React from 'react';
import { ArrowRight, Heart, Sparkles, MessageSquare } from 'lucide-react';

export const FeatureCardsSection: React.FC = () => {
  return (
    <section className="py-12">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
          {/* Card 1: Pastel Pink (Services) */}
          <article className="relative flex flex-col justify-between overflow-hidden rounded-[32px] border border-voak-pink/40 bg-voak-pink-light p-6 sm:p-8 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
            <div>
              <span className="text-[11px] font-extrabold tracking-wider text-[#D1348C] uppercase mb-2 block">
                Our Services
              </span>
              <h2 className="text-2xl font-bold tracking-tight text-voak-navy mb-3">
                Comprehensive Healthcare Solutions
              </h2>
              <p className="text-sm text-voak-navy/80 leading-relaxed mb-6">
                From clinical support to operational services, we help you focus on what matters most &mdash; better patient outcomes.
              </p>
              <a
                href="#services"
                className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-xs font-bold text-voak-navy shadow-sm hover:bg-voak-navy hover:text-white transition-colors"
              >
                <span>View All Services</span>
                <ArrowRight className="h-4 w-4" />
              </a>
            </div>

            <div className="relative mt-6 flex justify-end">
              <div className="relative overflow-hidden rounded-2xl border-2 border-white shadow-md max-w-[140px]">
                <img
                  src="/images/male_clinician.jpg"
                  alt="Specialist Healthcare Professional"
                  className="h-36 w-full object-cover"
                  width={140}
                  height={144}
                  loading="lazy"
                />
                <div className="absolute bottom-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-blue-500 text-white shadow-sm">
                  <Heart className="h-3.5 w-3.5 fill-white" />
                </div>
              </div>
            </div>
          </article>

          {/* Card 2: Warm Yellow (Industries) */}
          <article className="relative flex flex-col justify-between overflow-hidden rounded-[32px] border border-voak-yellow/50 bg-voak-yellow-light p-6 sm:p-8 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
            <div>
              <span className="text-[11px] font-extrabold tracking-wider text-[#9A7B00] uppercase mb-2 block">
                Industries
              </span>
              <h2 className="text-2xl font-bold tracking-tight text-voak-navy mb-3">
                We Work Across Healthcare Sectors
              </h2>
              <p className="text-sm text-voak-navy/80 leading-relaxed mb-6">
                Supporting hospitals, aged care, clinics, and healthcare groups across Australia and beyond.
              </p>
              <a
                href="#industries"
                className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-xs font-bold text-voak-navy shadow-sm hover:bg-voak-navy hover:text-white transition-colors"
              >
                <span>Explore Industries</span>
                <ArrowRight className="h-4 w-4" />
              </a>
            </div>

            <div className="relative mt-6 flex justify-end">
              <div className="relative overflow-hidden rounded-2xl border-2 border-white shadow-md max-w-[140px]">
                <img
                  src="/images/hospital_building.jpg"
                  alt="Modern Hospital Facility"
                  className="h-36 w-full object-cover"
                  width={140}
                  height={144}
                  loading="lazy"
                />
                <div className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-white shadow-sm">
                  <Sparkles className="h-3.5 w-3.5" />
                </div>
              </div>
            </div>
          </article>

          {/* Card 3: Mint Green (Partner With Us) */}
          <article className="relative flex flex-col justify-between overflow-hidden rounded-[32px] border border-voak-mint/40 bg-voak-mint-light p-6 sm:p-8 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
            <div>
              <span className="text-[11px] font-extrabold tracking-wider text-[#1F7A4D] uppercase mb-2 block">
                Partner With Us
              </span>
              <h2 className="text-2xl font-bold tracking-tight text-voak-navy mb-3">
                Grow Together
              </h2>
              <p className="text-sm text-voak-navy/80 leading-relaxed mb-6">
                Let&apos;s build stronger healthcare partnerships for a healthier future with governed operational reliability.
              </p>
              <a
                href="#contact"
                className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-xs font-bold text-voak-navy shadow-sm hover:bg-voak-navy hover:text-white transition-colors"
              >
                <span>Partner With Us</span>
                <ArrowRight className="h-4 w-4" />
              </a>
            </div>

            <div className="relative mt-6 flex justify-end">
              <div className="relative overflow-hidden rounded-2xl border-2 border-white shadow-md max-w-[140px]">
                <img
                  src="/images/partnership_consultation.jpg"
                  alt="Healthcare Executive Consultation"
                  className="h-36 w-full object-cover"
                  width={140}
                  height={144}
                  loading="lazy"
                />
                <div className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm">
                  <MessageSquare className="h-3.5 w-3.5" />
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>
  );
};
