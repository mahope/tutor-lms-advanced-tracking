'use client';

import { useState, useEffect, useCallback, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';

const API_URL = process.env.NEXT_PUBLIC_LICENSE_API || 'https://licenses.holstjensen.eu';

interface Activation {
  domain: string;
  siteUrl: string;
  activatedAt: string;
  lastHeartbeat: string;
  wpVersion: string;
  pluginVersion: string;
}

interface License {
  id: number;
  licenseKey: string;
  plan: string;
  status: 'active' | 'expired';
  maxActivations: number;
  expiresAt: string | null;
  createdAt: string;
  product: {
    name: string;
    slug: string;
    currentVersion: string;
  } | null;
  activations: Activation[];
}

interface LicensesResponse {
  success: boolean;
  email: string;
  licenses: License[];
}

function PortalContent() {
  const searchParams = useSearchParams();
  const token = searchParams.get('token');
  
  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [licenses, setLicenses] = useState<License[] | null>(null);
  const [customerEmail, setCustomerEmail] = useState<string | null>(null);
  const [downloadLoading, setDownloadLoading] = useState<string | null>(null);

  // Auto-load licenses if token is present
  const loadLicenses = useCallback(async (authToken: string) => {
    setIsLoading(true);
    try {
      const response = await fetch(`${API_URL}/api/v1/customer/licenses?token=${encodeURIComponent(authToken)}`);
      const data: LicensesResponse = await response.json();
      
      if (data.success) {
        setLicenses(data.licenses);
        setCustomerEmail(data.email);
      } else {
        setMessage({ type: 'error', text: 'Invalid or expired access link. Please request a new one.' });
      }
    } catch {
      setMessage({ type: 'error', text: 'Failed to load licenses. Please try again.' });
    }
    setIsLoading(false);
  }, []);

  useEffect(() => {
    if (token) {
      loadLicenses(token);
    }
  }, [token, loadLicenses]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setMessage(null);

    try {
      const response = await fetch(`${API_URL}/api/v1/customer/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      
      const data = await response.json();
      
      if (data.success) {
        setMessage({ type: 'success', text: 'Check your email for a login link! (It expires in 30 minutes)' });
        setEmail('');
      } else {
        setMessage({ type: 'error', text: data.message || 'Something went wrong. Please try again.' });
      }
    } catch {
      setMessage({ type: 'error', text: 'Failed to send login link. Please try again.' });
    }
    
    setIsLoading(false);
  };

  const handleDownload = async (productSlug: string) => {
    if (!token) return;
    setDownloadLoading(productSlug);
    
    try {
      const response = await fetch(`${API_URL}/api/v1/customer/download?token=${encodeURIComponent(token)}&product=${productSlug}`);
      const data = await response.json();
      
      if (data.success && data.downloadUrl) {
        window.open(data.downloadUrl, '_blank');
      } else {
        alert(data.message || 'Download failed');
      }
    } catch {
      alert('Failed to generate download link');
    }
    
    setDownloadLoading(null);
  };

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  // If we have licenses loaded, show the dashboard
  if (licenses !== null) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
        <div className="container mx-auto px-4 py-12 max-w-5xl">
          {/* Header */}
          <div className="text-center mb-12">
            <h1 className="text-4xl font-bold text-white mb-2">📊 License Portal</h1>
            <p className="text-purple-200">Logged in as <span className="font-mono">{customerEmail}</span></p>
          </div>

          {licenses.length === 0 ? (
            <div className="bg-white/10 backdrop-blur rounded-xl p-8 text-center">
              <p className="text-purple-200 text-lg">No licenses found for this email.</p>
              <p className="text-purple-300 mt-2">
                <a href="/#pricing" className="underline hover:text-white">Purchase a license</a> to get started.
              </p>
            </div>
          ) : (
            <div className="space-y-6">
              {licenses.map((license) => (
                <div key={license.id} className="bg-white/10 backdrop-blur rounded-xl p-6 border border-white/10">
                  {/* License Header */}
                  <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                      <div className="flex items-center gap-3 mb-2">
                        <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                          license.status === 'active' 
                            ? 'bg-green-500/20 text-green-300' 
                            : 'bg-red-500/20 text-red-300'
                        }`}>
                          {license.status === 'active' ? '✓ Active' : '✗ Expired'}
                        </span>
                        <span className="px-3 py-1 rounded-full text-sm font-medium bg-purple-500/20 text-purple-300 capitalize">
                          {license.plan}
                        </span>
                      </div>
                      <h2 className="text-xl font-semibold text-white">
                        {license.product?.name || 'Tutor LMS Advanced Tracking'}
                      </h2>
                    </div>
                    
                    {license.product && (
                      <button
                        onClick={() => handleDownload(license.product!.slug)}
                        disabled={downloadLoading === license.product.slug}
                        className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg font-medium hover:from-purple-500 hover:to-indigo-500 transition-all disabled:opacity-50"
                      >
                        {downloadLoading === license.product.slug ? (
                          <>⏳ Generating...</>
                        ) : (
                          <>⬇️ Download v{license.product.currentVersion}</>
                        )}
                      </button>
                    )}
                  </div>

                  {/* License Key */}
                  <div className="bg-slate-800/50 rounded-lg p-4 mb-6">
                    <div className="text-sm text-purple-300 mb-1">License Key</div>
                    <code className="text-lg font-mono text-white tracking-wider">{license.licenseKey}</code>
                  </div>

                  {/* License Info Grid */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div className="bg-slate-800/30 rounded-lg p-3">
                      <div className="text-xs text-purple-300 mb-1">Max Sites</div>
                      <div className="text-white font-semibold">{license.maxActivations}</div>
                    </div>
                    <div className="bg-slate-800/30 rounded-lg p-3">
                      <div className="text-xs text-purple-300 mb-1">Active Sites</div>
                      <div className="text-white font-semibold">{license.activations.length}</div>
                    </div>
                    <div className="bg-slate-800/30 rounded-lg p-3">
                      <div className="text-xs text-purple-300 mb-1">Purchased</div>
                      <div className="text-white font-semibold">{formatDate(license.createdAt)}</div>
                    </div>
                    <div className="bg-slate-800/30 rounded-lg p-3">
                      <div className="text-xs text-purple-300 mb-1">Expires</div>
                      <div className="text-white font-semibold">
                        {license.plan === 'lifetime' ? 'Never' : formatDate(license.expiresAt)}
                      </div>
                    </div>
                  </div>

                  {/* Activations */}
                  {license.activations.length > 0 && (
                    <div>
                      <h3 className="text-sm font-medium text-purple-300 mb-3">Active Installations</h3>
                      <div className="space-y-2">
                        {license.activations.map((activation, idx) => (
                          <div key={idx} className="bg-slate-800/30 rounded-lg p-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                              <div className="text-white font-medium">{activation.domain}</div>
                              <div className="text-xs text-purple-300">
                                WP {activation.wpVersion} • Plugin v{activation.pluginVersion}
                              </div>
                            </div>
                            <div className="text-xs text-purple-400">
                              Last seen: {formatDate(activation.lastHeartbeat)}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}

          {/* Support Section */}
          <div className="mt-12 bg-white/5 rounded-xl p-6 border border-white/10">
            <h3 className="text-lg font-semibold text-white mb-4">💬 Need Help?</h3>
            <div className="grid md:grid-cols-3 gap-4">
              <a 
                href="https://tutor-tracking.com/docs" 
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-3 bg-slate-800/50 rounded-lg p-4 hover:bg-slate-800/70 transition-colors"
              >
                <span className="text-2xl">📚</span>
                <div>
                  <div className="text-white font-medium">Documentation</div>
                  <div className="text-sm text-purple-300">Setup guides & tutorials</div>
                </div>
              </a>
              <a 
                href="mailto:support@tutor-tracking.com"
                className="flex items-center gap-3 bg-slate-800/50 rounded-lg p-4 hover:bg-slate-800/70 transition-colors"
              >
                <span className="text-2xl">📧</span>
                <div>
                  <div className="text-white font-medium">Email Support</div>
                  <div className="text-sm text-purple-300">support@tutor-tracking.com</div>
                </div>
              </a>
              <a 
                href="https://tutor-tracking.com/changelog" 
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-3 bg-slate-800/50 rounded-lg p-4 hover:bg-slate-800/70 transition-colors"
              >
                <span className="text-2xl">📋</span>
                <div>
                  <div className="text-white font-medium">Changelog</div>
                  <div className="text-sm text-purple-300">What&apos;s new</div>
                </div>
              </a>
            </div>
          </div>

          {/* Footer */}
          <div className="mt-8 text-center">
            <a href="/" className="text-purple-300 hover:text-white transition-colors">
              ← Back to tutor-tracking.com
            </a>
          </div>
        </div>
      </div>
    );
  }

  // Login form
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold text-white mb-2">📊 License Portal</h1>
          <p className="text-purple-200">Access your licenses, downloads, and support</p>
        </div>

        <div className="bg-white/10 backdrop-blur rounded-xl p-8 border border-white/10">
          <form onSubmit={handleLogin}>
            <label htmlFor="email" className="block text-sm font-medium text-purple-200 mb-2">
              Email Address
            </label>
            <input
              type="email"
              id="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              required
              className="w-full px-4 py-3 rounded-lg bg-slate-800/50 border border-white/10 text-white placeholder-purple-300/50 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            />
            
            <button
              type="submit"
              disabled={isLoading}
              className="w-full mt-4 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg font-medium hover:from-purple-500 hover:to-indigo-500 transition-all disabled:opacity-50"
            >
              {isLoading ? 'Sending...' : 'Send Login Link'}
            </button>
          </form>

          {message && (
            <div className={`mt-4 p-4 rounded-lg ${
              message.type === 'success' 
                ? 'bg-green-500/20 text-green-200 border border-green-500/30' 
                : 'bg-red-500/20 text-red-200 border border-red-500/30'
            }`}>
              {message.text}
            </div>
          )}

          <p className="mt-6 text-center text-sm text-purple-300">
            We&apos;ll send you a secure link to access your licenses.
            <br />No password needed!
          </p>
        </div>

        <div className="mt-8 text-center">
          <a href="/" className="text-purple-300 hover:text-white transition-colors">
            ← Back to tutor-tracking.com
          </a>
        </div>
      </div>
    </div>
  );
}

function LoadingPortal() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 flex items-center justify-center">
      <div className="text-center">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-purple-500 mx-auto mb-4"></div>
        <p className="text-purple-200">Loading portal...</p>
      </div>
    </div>
  );
}

export default function PortalPage() {
  return (
    <Suspense fallback={<LoadingPortal />}>
      <PortalContent />
    </Suspense>
  );
}
