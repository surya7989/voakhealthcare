import React, { useState } from 'react';
import { Menu, ArrowRight, ShieldCheck, HeartHandshake } from 'lucide-react';
import { Button } from '../../../shared/components/Button';
import { Drawer } from '../../../shared/components/Drawer';
import { Link, useRouter } from '../../../shared/router/Router';

export const PublicHeader: React.FC = () => {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const { path } = useRouter();
  const pathname = path.split('?')[0].split('#')[0];
  const isHomePage = pathname === '/' || pathname === '';

  const navLinks = isHomePage
    ? [
        { label: 'Home', href: '#hero', isRoute: false },
        { label: 'About', href: '#about', isRoute: false },
        { label: 'Services', href: '#services', isRoute: false },
        { label: 'Industries', href: '#industries', isRoute: false },
        { label: 'How We Work', href: '#how-we-work', isRoute: false },
        { label: 'Contact', href: '#contact', isRoute: false },
      ]
    : [
        { label: 'Home', href: '/', isRoute: true },
        { label: 'About', href: '/about', isRoute: true },
        { label: 'Why Choose Us', href: '/why-choose-voak', isRoute: true },
        { label: 'How We Work', href: '/how-we-work', isRoute: true },
        { label: 'Compliance', href: '/quality-compliance', isRoute: true },
        { label: 'Services', href: '/#services', isRoute: false },
        { label: 'Industries', href: '/#industries', isRoute: false },
      ];


  return (
    <>
      <header
        role="banner"
        className="sticky top-0 z-40 w-full border-b border-voak-border bg-white/85 backdrop-blur-md transition-all duration-200"
      >
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
          {/* Logo Lockup */}
          {isHomePage ? (
            <a
              href="#hero"
              className="flex items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple rounded-xl p-1"
              aria-label="Voak Health Care Homepage"
            >
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-voak-canvas border border-voak-border/60 shadow-sm">
                <svg className="h-7 w-7" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                  <circle cx="20" cy="20" r="18" fill="#F2F0F8" />
                  <path d="M12 24 C14 14 26 14 28 24 C24 30 16 30 12 24 Z" fill="#665CE9" />
                  <circle cx="20" cy="14" r="5" fill="#EEAFD7" />
                  <path d="M8 20 C14 28 26 28 32 20" stroke="#FBDC62" strokeWidth="3" strokeLinecap="round" />
                </svg>
              </div>
              <div className="flex flex-col">
                <span className="text-lg font-extrabold tracking-tight text-voak-navy leading-none">
                  VOAK
                </span>
                <span className="text-[10px] font-bold tracking-widest text-voak-purple uppercase mt-0.5">
                  HEALTH CARE
                </span>
              </div>
            </a>
          ) : (
            <Link
              to="/"
              className="flex items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple rounded-xl p-1"
              aria-label="Voak Health Care Homepage"
            >
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-voak-canvas border border-voak-border/60 shadow-sm">
                <svg className="h-7 w-7" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                  <circle cx="20" cy="20" r="18" fill="#F2F0F8" />
                  <path d="M12 24 C14 14 26 14 28 24 C24 30 16 30 12 24 Z" fill="#665CE9" />
                  <circle cx="20" cy="14" r="5" fill="#EEAFD7" />
                  <path d="M8 20 C14 28 26 28 32 20" stroke="#FBDC62" strokeWidth="3" strokeLinecap="round" />
                </svg>
              </div>
              <div className="flex flex-col">
                <span className="text-lg font-extrabold tracking-tight text-voak-navy leading-none">
                  VOAK
                </span>
                <span className="text-[10px] font-bold tracking-widest text-voak-purple uppercase mt-0.5">
                  HEALTH CARE
                </span>
              </div>
            </Link>
          )}

          {/* Desktop Navigation Links */}
          <nav className="hidden lg:flex items-center gap-1 xl:gap-2" aria-label="Main Navigation">
            {navLinks.map((link) => {
              const isActive = link.isRoute && pathname === link.href;
              const linkClasses = `rounded-xl px-3 py-2 text-xs xl:text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple ${
                isActive
                  ? 'text-voak-purple bg-voak-purple-light/70 font-bold'
                  : 'text-voak-navy/80 hover:text-voak-purple hover:bg-voak-purple-light/40'
              }`;

              if (link.isRoute) {
                return (
                  <Link key={link.label} to={link.href} className={linkClasses}>
                    {link.label}
                  </Link>
                );
              }

              return (
                <a key={link.label} href={link.href} className={linkClasses}>
                  {link.label}
                </a>
              );
            })}
          </nav>

          {/* Desktop Right Actions */}
          <div className="hidden lg:flex items-center gap-3">
            <Link to="/admin">
              <Button
                variant="ghost"
                size="sm"
                className="text-xs text-voak-muted hover:text-voak-navy gap-1.5"
                leftIcon={<ShieldCheck className="h-4 w-4" />}
              >
                Staff Portal
              </Button>
            </Link>

            <a href={isHomePage ? '#contact' : '/#contact'}>
              <Button
                variant="primary"
                size="md"
                className="shadow-sm font-semibold"
                leftIcon={<HeartHandshake className="h-4 w-4" />}
              >
                Partner With Us
              </Button>
            </a>
          </div>

          {/* Mobile Menu Button */}
          <div className="flex lg:hidden items-center gap-2">
            <button
              type="button"
              onClick={() => setIsMobileMenuOpen(true)}
              aria-label="Open navigation menu"
              className="flex h-11 w-11 items-center justify-center rounded-xl border border-voak-border bg-white text-voak-navy hover:bg-voak-canvas transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple"
            >
              <Menu className="h-6 w-6" aria-hidden="true" />
            </button>
          </div>
        </div>
      </header>

      {/* Mobile Navigation Drawer */}
      <Drawer
        isOpen={isMobileMenuOpen}
        onClose={() => setIsMobileMenuOpen(false)}
        position="right"
        size="sm"
        title="Voak Health Care"
        description="Enterprise Healthcare Solutions"
      >
        <div className="flex flex-col gap-2 pt-2">
          {navLinks.map((link) => {
            const isActive = link.isRoute && pathname === link.href;
            if (link.isRoute) {
              return (
                <Link
                  key={link.label}
                  to={link.href}
                  onClick={() => setIsMobileMenuOpen(false)}
                  className={`flex items-center justify-between rounded-xl p-3 text-base font-semibold transition-colors ${
                    isActive
                      ? 'bg-voak-purple-light text-voak-purple'
                      : 'text-voak-navy hover:bg-voak-purple-light/50 hover:text-voak-purple'
                  }`}
                >
                  <span>{link.label}</span>
                  <ArrowRight className="h-4 w-4 text-voak-muted" />
                </Link>
              );
            }

            return (
              <a
                key={link.label}
                href={link.href}
                onClick={() => setIsMobileMenuOpen(false)}
                className="flex items-center justify-between rounded-xl p-3 text-base font-semibold text-voak-navy hover:bg-voak-purple-light/50 hover:text-voak-purple transition-colors"
              >
                <span>{link.label}</span>
                <ArrowRight className="h-4 w-4 text-voak-muted" />
              </a>
            );
          })}

          <div className="my-4 h-px bg-voak-border" />

          <a href={isHomePage ? '#contact' : '/#contact'} onClick={() => setIsMobileMenuOpen(false)}>
            <Button variant="primary" size="lg" className="w-full justify-center shadow-sm">
              Partner With Us
            </Button>
          </a>

          <Link to="/admin" onClick={() => setIsMobileMenuOpen(false)} className="mt-2">
            <Button variant="outline" size="md" className="w-full justify-center gap-2">
              <ShieldCheck className="h-4 w-4" />
              <span>Staff Admin Portal</span>
            </Button>
          </Link>
        </div>
      </Drawer>
    </>
  );
};
