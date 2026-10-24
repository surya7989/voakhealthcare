import React from 'react';
import { ShieldCheck } from 'lucide-react';
import { Link } from '../../../shared/router/Router';

export const PublicFooter: React.FC = () => {
  return (
    <footer
      role="contentinfo"
      className="border-t border-voak-border bg-white pt-16 pb-12 transition-colors"
    >
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8 pb-12 border-b border-voak-border">
          {/* Column 1: Brand Info */}
          <div className="lg:col-span-1 flex flex-col">
            <div className="flex items-center gap-3 mb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-voak-canvas border border-voak-border/60 shadow-sm">
                <svg className="h-6 w-6" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                  <circle cx="20" cy="20" r="18" fill="#F2F0F8" />
                  <path d="M12 24 C14 14 26 14 28 24 C24 30 16 30 12 24 Z" fill="#665CE9" />
                  <circle cx="20" cy="14" r="5" fill="#EEAFD7" />
                  <path d="M8 20 C14 28 26 28 32 20" stroke="#FBDC62" strokeWidth="3" strokeLinecap="round" />
                </svg>
              </div>
              <div className="flex flex-col">
                <span className="text-base font-extrabold tracking-tight text-voak-navy leading-none">
                  VOAK
                </span>
                <span className="text-[9px] font-bold tracking-widest text-voak-purple uppercase mt-0.5">
                  HEALTH CARE
                </span>
              </div>
            </div>

            <p className="text-xs text-voak-muted leading-relaxed mb-4">
              Enterprise B2B healthcare workforce solutions and operational infrastructure partner across Australia.
            </p>

            <div className="text-[11px] text-voak-muted leading-relaxed space-y-1">
              <p>Corporate Operations &bull; Sydney, NSW, Australia</p>
              <p>contact@voakhealthcare.com.au</p>
              <p className="text-[10px] text-voak-muted/80 pt-1">
                Clinical Governance &bull; NSQHS Aligned
              </p>
            </div>
          </div>

          {/* Column 2: Services */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-4">
              Services
            </h4>
            <ul className="space-y-2.5 text-xs text-voak-muted font-medium">
              <li>
                <a href="#services" className="hover:text-voak-purple transition-colors">
                  Clinical Workforce & Staffing
                </a>
              </li>
              <li>
                <a href="#services" className="hover:text-voak-purple transition-colors">
                  Operations & Facility BPO
                </a>
              </li>
              <li>
                <a href="#services" className="hover:text-voak-purple transition-colors">
                  Governance & Compliance
                </a>
              </li>
              <li>
                <a href="#services" className="hover:text-voak-purple transition-colors">
                  Allied Health Infrastructure
                </a>
              </li>
              <li>
                <a href="#services" className="hover:text-voak-purple transition-colors">
                  Roster Surge Relief
                </a>
              </li>
            </ul>
          </div>

          {/* Column 3: Industries */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-4">
              Industries
            </h4>
            <ul className="space-y-2.5 text-xs text-voak-muted font-medium">
              <li>
                <a href="#industries" className="hover:text-voak-purple transition-colors">
                  Public & Private Hospitals
                </a>
              </li>
              <li>
                <a href="#industries" className="hover:text-voak-purple transition-colors">
                  Residential Aged Care
                </a>
              </li>
              <li>
                <a href="#industries" className="hover:text-voak-purple transition-colors">
                  Community Health Services
                </a>
              </li>
              <li>
                <a href="#industries" className="hover:text-voak-purple transition-colors">
                  Corporate & Industrial Clinics
                </a>
              </li>
            </ul>
          </div>

          {/* Column 4: Governance */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-4">
              Governance
            </h4>
            <ul className="space-y-2.5 text-xs text-voak-muted font-medium">
              <li>
                <a href="#about" className="hover:text-voak-purple transition-colors">
                  Quality & Safety Frameworks
                </a>
              </li>
              <li>
                <a href="#about" className="hover:text-voak-purple transition-colors">
                  Clinical Credentialing Rigor
                </a>
              </li>
              <li>
                <a href="#contact" className="hover:text-voak-purple transition-colors">
                  Privacy Policy & Compliance
                </a>
              </li>
              <li>
                <a href="#contact" className="hover:text-voak-purple transition-colors">
                  Terms of Service & SLAs
                </a>
              </li>
            </ul>
          </div>

          {/* Column 5: Administration & Portals */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-4">
              Administration
            </h4>
            <ul className="space-y-2.5 text-xs text-voak-muted font-medium">
              <li>
                <Link
                  to="/admin"
                  className="inline-flex items-center gap-1.5 text-voak-purple font-semibold hover:underline"
                >
                  <ShieldCheck className="h-3.5 w-3.5" />
                  <span>Admin Operations Portal</span>
                </Link>
              </li>
              <li>
                <a href="#contact" className="hover:text-voak-purple transition-colors">
                  Careers & Placements
                </a>
              </li>
              <li>
                <a href="#contact" className="hover:text-voak-purple transition-colors">
                  Partner Helpdesk
                </a>
              </li>
              <li>
                <a href="#how-we-work" className="hover:text-voak-purple transition-colors">
                  Operating Model
                </a>
              </li>
            </ul>
          </div>
        </div>

        {/* Bottom Bar */}
        <div className="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-voak-muted">
          <div>
            &copy; 2026 Voak Health Care. All rights reserved.
          </div>

          <div className="flex items-center gap-6">
            <a href="#contact" className="hover:text-voak-navy transition-colors">
              Privacy Policy
            </a>
            <a href="#contact" className="hover:text-voak-navy transition-colors">
              Terms of Engagement
            </a>
            <a href="#contact" className="hover:text-voak-navy transition-colors">
              Security
            </a>
          </div>
        </div>
      </div>
    </footer>
  );
};
