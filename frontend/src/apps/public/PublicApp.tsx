import React from 'react';
import { Sparkles, Shield, ArrowRight, Activity, CheckCircle2 } from 'lucide-react';
import { Button } from '../../shared/components/Button';
import { Badge } from '../../shared/components/Badge';
import { Link } from '../../shared/router/Router';

export const PublicApp: React.FC = () => {
  return (
    <div className="min-h-screen bg-neutral-900 text-white flex flex-col justify-between selection:bg-[#6A0DAD] selection:text-white">
      {/* Top Navigation */}
      <header className="border-b border-neutral-800/80 bg-neutral-950/60 backdrop-blur sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-[#6A0DAD] to-[#D8B4E2] text-white shadow-md shadow-[#6A0DAD]/30">
              <Sparkles className="h-5 w-5" />
            </div>
            <span className="font-bold text-lg tracking-tight">VOAK Health Care</span>
          </div>

          <div className="flex items-center gap-3">
            <Link to="/admin">
              <Button
                variant="outline"
                size="sm"
                className="border-neutral-700 bg-neutral-800/50 hover:bg-neutral-800 text-neutral-200"
                rightIcon={<ArrowRight className="h-4 w-4" />}
              >
                Staff Admin Portal
              </Button>
            </Link>
          </div>
        </div>
      </header>

      {/* Main Hero / Status */}
      <main className="flex-1 flex items-center justify-center px-4 py-16">
        <div className="max-w-3xl mx-auto text-center">
          <Badge variant="purple" size="md" className="mb-6 font-semibold px-3 py-1">
            Phase 18 — React Admin Foundation Active
          </Badge>

          <h1 className="text-4xl sm:text-6xl font-extrabold tracking-tight leading-tight sm:leading-none mb-6">
            Pioneering Global <br />
            <span className="bg-gradient-to-r from-[#D8B4E2] via-[#E6E6FA] to-white bg-clip-text text-transparent">
              Healthcare Technology
            </span>
          </h1>

          <p className="text-base sm:text-lg text-neutral-400 max-w-2xl mx-auto mb-10 leading-relaxed">
            The VOAK Health Care digital infrastructure is actively being developed. Backend foundations (Stage 2) are 100% complete with 401 verified tests. The public web application is scheduled for Phase 20–24.
          </p>

          <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
            <Link to="/admin">
              <Button
                variant="primary"
                size="lg"
                className="bg-[#6A0DAD] hover:bg-[#5B0B96] text-white font-semibold px-8"
                rightIcon={<ArrowRight className="h-4 w-4" />}
              >
                Access Admin Portal
              </Button>
            </Link>
          </div>

          {/* Architecture badges */}
          <div className="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
            <div className="p-4 rounded-xl border border-neutral-800 bg-neutral-950/40">
              <div className="flex items-center gap-2 text-emerald-400 text-xs font-semibold mb-1">
                <CheckCircle2 className="h-4 w-4" />
                <span>Backend Verified</span>
              </div>
              <p className="text-xs text-neutral-400">401 tests, 2,032 assertions, 28 database migrations locked.</p>
            </div>
            <div className="p-4 rounded-xl border border-neutral-800 bg-neutral-950/40">
              <div className="flex items-center gap-2 text-[#D8B4E2] text-xs font-semibold mb-1">
                <Shield className="h-4 w-4" />
                <span>Security Hardened</span>
              </div>
              <p className="text-xs text-neutral-400">HttpOnly session auth, CSRF headers, and RBAC permission guards.</p>
            </div>
            <div className="p-4 rounded-xl border border-neutral-800 bg-neutral-950/40">
              <div className="flex items-center gap-2 text-blue-400 text-xs font-semibold mb-1">
                <Activity className="h-4 w-4" />
                <span>Design System</span>
              </div>
              <p className="text-xs text-neutral-400">Atomic design tokens and accessible WCAG 2.1 AA UI primitives.</p>
            </div>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-neutral-800/80 py-6 text-center text-xs text-neutral-400">
        <div className="max-w-7xl mx-auto px-4">
          © {new Date().getFullYear()} VOAK Health Care. All rights reserved.
        </div>
      </footer>
    </div>
  );
};
