import React, { useState } from 'react';
import { Sparkles, Lock, Mail, KeyRound } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useRouter } from '../../../shared/router/Router';
import { Input } from '../../../shared/components/Input';
import { Button } from '../../../shared/components/Button';
import { Alert } from '../../../shared/components/Alert';
import { FormField } from '../../../shared/components/FormField';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../../../shared/components/Card';

export const AdminLoginPage: React.FC = () => {
  const { login, isAuthenticated } = useAuth();
  const { navigate } = useRouter();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totpCode, setTotpCode] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // If already authenticated, redirect to dashboard
  React.useEffect(() => {
    if (isAuthenticated) {
      navigate('/admin');
    }
  }, [isAuthenticated, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!email || !password) {
      setErrorMessage('Please enter both your email and password.');
      return;
    }

    setIsLoading(true);
    try {
      await login(email, password, totpCode ? totpCode : undefined);
      navigate('/admin');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Login failed. Please verify your credentials and try again.';
      setErrorMessage(msg);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-neutral-900 px-4 py-12 relative overflow-hidden">
      {/* Subtle background glow */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-[#6A0DAD]/15 rounded-full blur-3xl pointer-events-none" />

      <div className="w-full max-w-md relative z-10">
        {/* Brand header */}
        <div className="flex flex-col items-center text-center mb-8">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-[#6A0DAD] to-[#D8B4E2] text-white shadow-xl shadow-[#6A0DAD]/40 mb-4">
            <Sparkles className="h-7 w-7" />
          </div>
          <h1 className="text-2xl font-bold text-white tracking-tight">VOAK Health Care</h1>
          <p className="text-sm text-neutral-400 mt-1">Administrative Operations Portal</p>
        </div>

        <Card className="border-neutral-800 bg-neutral-950/80 backdrop-blur shadow-2xl">
          <CardHeader className="text-center pb-2">
            <CardTitle className="text-xl text-white">Sign In to Admin</CardTitle>
            <CardDescription className="text-neutral-400">
              Enter your authorized staff credentials to continue
            </CardDescription>
          </CardHeader>

          <CardContent className="pt-4">
            {errorMessage && (
              <Alert variant="error" className="mb-5" onClose={() => setErrorMessage(null)}>
                {errorMessage}
              </Alert>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <FormField label="Email Address" required>
                <div className="relative">
                  <Input
                    type="email"
                    name="email"
                    id="admin-email"
                    autoComplete="email"
                    required
                    placeholder="name@voakhealthcare.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    disabled={isLoading}
                    className="pl-10 bg-neutral-900 border-neutral-800 text-white placeholder:text-neutral-600 focus:border-[#6A0DAD]"
                  />
                  <Mail className="absolute left-3 top-2.5 h-5 w-5 text-neutral-500" />
                </div>
              </FormField>

              <FormField label="Password" required>
                <div className="relative">
                  <Input
                    type="password"
                    name="password"
                    id="admin-password"
                    autoComplete="current-password"
                    required
                    placeholder="••••••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    disabled={isLoading}
                    className="pl-10 bg-neutral-900 border-neutral-800 text-white placeholder:text-neutral-600 focus:border-[#6A0DAD]"
                  />
                  <Lock className="absolute left-3 top-2.5 h-5 w-5 text-neutral-500" />
                </div>
              </FormField>

              <FormField
                label="Two-Factor TOTP Code"
                helperText="Required if 2FA is enabled on your staff account"
              >
                <div className="relative">
                  <Input
                    type="text"
                    name="totp_code"
                    id="admin-totp"
                    autoComplete="one-time-code"
                    inputMode="numeric"
                    placeholder="6-digit code (optional)"
                    value={totpCode}
                    onChange={(e) => setTotpCode(e.target.value)}
                    disabled={isLoading}
                    className="pl-10 bg-neutral-900 border-neutral-800 text-white placeholder:text-neutral-600 focus:border-[#6A0DAD]"
                  />
                  <KeyRound className="absolute left-3 top-2.5 h-5 w-5 text-neutral-500" />
                </div>
              </FormField>

              <div className="pt-2">
                <Button
                  type="submit"
                  variant="primary"
                  size="lg"
                  className="w-full bg-[#6A0DAD] hover:bg-[#5B0B96] text-white font-semibold"
                  isLoading={isLoading}
                >
                  Authenticate
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        {/* Security notice footer */}
        <p className="text-center text-xs text-neutral-400 mt-6 leading-relaxed">
          Authorized personnel only. All access attempts and administrative operations are cryptographically logged to an immutable audit ledger.
        </p>
      </div>
    </div>
  );
};
