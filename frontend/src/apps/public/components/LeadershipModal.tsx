import React, { useEffect, useRef } from 'react';
import { X, Award, Stethoscope, Linkedin, Shield } from 'lucide-react';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';

export interface LeaderProfile {
  id: string;
  name: string;
  role: string;
  credentials: string;
  category: 'Executive' | 'Clinical Advisory' | 'Operational';
  bio: string;
  governanceFocus?: string;
  imageSrc?: string;
  linkedinUrl?: string;
}

export interface LeadershipModalProps {
  leader: LeaderProfile | null;
  isOpen: boolean;
  onClose: () => void;
}

export const LeadershipModal: React.FC<LeadershipModalProps> = ({ leader, isOpen, onClose }) => {
  const modalRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  // Focus trapping and Escape key management
  useEffect(() => {
    if (!isOpen || !leader) return;

    // Focus initial element
    const timer = setTimeout(() => {
      closeButtonRef.current?.focus();
    }, 50);

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
        return;
      }

      if (e.key === 'Tab' && modalRef.current) {
        const focusableElements = modalRef.current.querySelectorAll<HTMLElement>(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const first = focusableElements[0];
        const last = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
          if (document.activeElement === first) {
            e.preventDefault();
            last?.focus();
          }
        } else {
          if (document.activeElement === last) {
            e.preventDefault();
            first?.focus();
          }
        }
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    // Lock body scroll
    const originalOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      clearTimeout(timer);
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = originalOverflow;
    };
  }, [isOpen, leader, onClose]);

  if (!isOpen || !leader) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-voak-navy/60 backdrop-blur-sm transition-opacity"
      onClick={(e) => {
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
      aria-hidden={!isOpen}
    >
      <div
        ref={modalRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="leadership-modal-title"
        aria-describedby="leadership-modal-role"
        className="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-voak-border focus:outline-none"
      >
        {/* Sticky Close Button */}
        <button
          ref={closeButtonRef}
          type="button"
          onClick={onClose}
          aria-label="Close executive biography modal"
          className="absolute top-5 right-5 flex h-10 w-10 items-center justify-center rounded-full bg-voak-canvas text-voak-navy hover:bg-voak-purple/10 hover:text-voak-purple focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple transition-colors"
        >
          <X className="h-5 w-5" aria-hidden="true" />
        </button>

        {/* Header Profile Area */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-5 pb-6 border-b border-voak-border">
          {/* Avatar / Photo with SVG Fallback */}
          <div className="h-20 w-20 sm:h-24 sm:w-24 rounded-2xl bg-voak-canvas border border-voak-border/80 flex items-center justify-center overflow-hidden shrink-0 shadow-inner">
            {leader.imageSrc ? (
              <img
                src={leader.imageSrc}
                alt={leader.name}
                className="h-full w-full object-cover"
                onError={(e) => {
                  // Fallback to SVG placeholder on load error
                  (e.currentTarget as HTMLElement).style.display = 'none';
                  e.currentTarget.parentElement?.querySelector('.fallback-avatar')?.classList.remove('hidden');
                }}
              />
            ) : null}
            <div
              className={`fallback-avatar ${leader.imageSrc ? 'hidden' : ''} flex flex-col items-center justify-center text-voak-purple`}
              aria-hidden="true"
            >
              <Stethoscope className="h-8 w-8 text-voak-purple/80" />
            </div>
          </div>

          <div className="flex-1 min-w-0 pr-8">
            <div className="flex items-center gap-2 mb-1.5 flex-wrap">
              <Badge variant="purple" className="text-[11px] font-semibold">
                {leader.category}
              </Badge>
              {leader.credentials && (
                <span className="text-xs font-bold text-voak-purple tracking-wide">
                  {leader.credentials}
                </span>
              )}
            </div>

            <h2
              id="leadership-modal-title"
              className="text-2xl font-extrabold text-voak-navy tracking-tight"
            >
              {leader.name}
            </h2>

            <p id="leadership-modal-role" className="text-sm font-semibold text-voak-muted mt-0.5">
              {leader.role}
            </p>
          </div>
        </div>

        {/* Modal Body Content */}
        <div className="py-6 space-y-6 text-sm text-voak-navy/90 leading-relaxed">
          <div>
            <h3 className="text-xs font-bold uppercase tracking-wider text-voak-muted mb-2 flex items-center gap-1.5">
              <Award className="h-4 w-4 text-voak-purple" aria-hidden="true" />
              <span>Executive & Clinical Background</span>
            </h3>
            <p className="whitespace-pre-line text-voak-muted font-normal leading-relaxed">
              {leader.bio}
            </p>
          </div>

          {leader.governanceFocus && (
            <div className="rounded-2xl bg-voak-canvas/60 p-4 border border-voak-border/70">
              <h3 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-1 flex items-center gap-1.5">
                <Shield className="h-4 w-4 text-voak-mint" aria-hidden="true" />
                <span>Governance & Institutional Oversight</span>
              </h3>
              <p className="text-xs text-voak-muted leading-relaxed">
                {leader.governanceFocus}
              </p>
            </div>
          )}
        </div>

        {/* Modal Footer Actions */}
        <div className="pt-4 border-t border-voak-border flex flex-wrap items-center justify-between gap-3">
          {leader.linkedinUrl ? (
            <a
              href={leader.linkedinUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 text-xs font-semibold text-voak-purple hover:underline"
            >
              <Linkedin className="h-4 w-4" aria-hidden="true" />
              <span>Connect on LinkedIn</span>
            </a>
          ) : (
            <span className="text-xs text-voak-muted">Institutional Healthcare Leadership</span>
          )}

          <Button variant="secondary" size="sm" onClick={onClose}>
            Close Biography
          </Button>
        </div>
      </div>
    </div>
  );
};
