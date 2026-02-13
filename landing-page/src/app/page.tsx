'use client'

import Link from 'next/link'

// Plausible event tracking
declare global {
  interface Window {
    plausible?: (event: string, options?: { props?: Record<string, string> }) => void
  }
}

const trackEvent = (event: string, props?: Record<string, string>) => {
  if (typeof window !== 'undefined' && window.plausible) {
    window.plausible(event, props ? { props } : undefined)
  }
}

export default function Home() {
  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <header className="fixed top-0 w-full bg-white/80 backdrop-blur-md border-b border-gray-100 z-50">
        <nav className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center">
              <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <span className="font-bold text-xl">TLAT</span>
          </div>
          <div className="hidden md:flex items-center gap-8">
            <a href="#features" className="text-gray-600 hover:text-gray-900 transition">Features</a>
            <a href="#compare" className="text-gray-600 hover:text-gray-900 transition">Compare</a>
            <a href="#pricing" className="text-gray-600 hover:text-gray-900 transition">Pricing</a>
            <a href="#faq" className="text-gray-600 hover:text-gray-900 transition">FAQ</a>
            <a href="https://docs.tutor-tracking.com" className="text-gray-600 hover:text-gray-900 transition">Docs</a>
            <Link href="/portal" className="text-gray-600 hover:text-gray-900 transition">My Licenses</Link>
          </div>
          <a 
            href="#pricing" 
            onClick={() => trackEvent('pricing_viewed', { source: 'header' })}
            className="bg-indigo-500 hover:bg-indigo-600 text-white px-5 py-2 rounded-lg font-medium transition"
          >
            Get Started
          </a>
        </nav>
      </header>

      {/* Hero Section */}
      <section className="pt-32 pb-20 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="inline-block mb-6 px-4 py-2 bg-indigo-50 rounded-full">
            <span className="text-indigo-600 font-medium">🚀 Now with Auto-Updates</span>
          </div>
          <h1 className="text-5xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
            Finally, <span className="text-indigo-500">Real Analytics</span> for Tutor LMS
          </h1>
          <p className="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
            Stop guessing why students drop out. Get actionable insights into course progress, completion rates, and problem areas — all in beautiful, real-time dashboards.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <a 
              href="#pricing" 
              onClick={() => trackEvent('pricing_viewed', { source: 'hero' })}
              className="bg-indigo-500 hover:bg-indigo-600 text-white px-8 py-4 rounded-xl font-semibold text-lg transition shadow-lg shadow-indigo-500/25"
            >
              Get Lifetime Access — €99
            </a>
            <a 
              href="#demo" 
              onClick={() => trackEvent('demo_clicked')}
              className="border-2 border-gray-200 hover:border-gray-300 text-gray-700 px-8 py-4 rounded-xl font-semibold text-lg transition"
            >
              View Demo →
            </a>
          </div>
          <p className="mt-6 text-sm text-gray-500">
            ✓ 30-day money-back guarantee &nbsp;&nbsp; ✓ Works with Tutor LMS Free & Pro
          </p>
        </div>
      </section>

      {/* Dashboard Preview */}
      <section className="pb-20 px-4">
        <div className="max-w-5xl mx-auto">
          <div className="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl p-2 shadow-2xl">
            <div className="bg-gray-900 rounded-xl p-6">
              <div className="flex gap-2 mb-4">
                <div className="w-3 h-3 rounded-full bg-red-500"></div>
                <div className="w-3 h-3 rounded-full bg-yellow-500"></div>
                <div className="w-3 h-3 rounded-full bg-green-500"></div>
              </div>
              <div className="grid grid-cols-3 gap-4 mb-6">
                <div className="bg-gray-800 rounded-lg p-4">
                  <p className="text-gray-400 text-sm">Active Students</p>
                  <p className="text-2xl font-bold text-white">2,847</p>
                  <p className="text-green-400 text-sm">↑ 12% this month</p>
                </div>
                <div className="bg-gray-800 rounded-lg p-4">
                  <p className="text-gray-400 text-sm">Avg. Completion</p>
                  <p className="text-2xl font-bold text-white">73%</p>
                  <p className="text-green-400 text-sm">↑ 5% vs. last month</p>
                </div>
                <div className="bg-gray-800 rounded-lg p-4">
                  <p className="text-gray-400 text-sm">Quiz Pass Rate</p>
                  <p className="text-2xl font-bold text-white">89%</p>
                  <p className="text-gray-400 text-sm">Industry avg: 67%</p>
                </div>
              </div>
              <div className="bg-gray-800 rounded-lg p-4 h-48 flex items-center justify-center">
                <div className="w-full max-w-md">
                  <div className="flex justify-between text-gray-400 text-sm mb-2">
                    <span>Course Completion Funnel</span>
                    <span>Last 30 days</span>
                  </div>
                  <div className="space-y-2">
                    <div className="flex items-center gap-3">
                      <span className="text-gray-400 text-sm w-20">Enrolled</span>
                      <div className="flex-1 bg-indigo-500 h-6 rounded"></div>
                      <span className="text-white text-sm">100%</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-gray-400 text-sm w-20">Started</span>
                      <div className="flex-1 bg-indigo-500 h-6 rounded" style={{width: '85%'}}></div>
                      <span className="text-white text-sm">85%</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-gray-400 text-sm w-20">Midpoint</span>
                      <div className="flex-1 bg-indigo-500 h-6 rounded" style={{width: '62%'}}></div>
                      <span className="text-white text-sm">62%</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-gray-400 text-sm w-20">Completed</span>
                      <div className="flex-1 bg-green-500 h-6 rounded" style={{width: '48%'}}></div>
                      <span className="text-white text-sm">48%</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Social Proof */}
      <section className="py-12 bg-gray-50 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <p className="text-gray-500 mb-6">Trusted by course creators worldwide</p>
          <div className="flex flex-wrap justify-center items-center gap-8 opacity-60">
            <span className="text-2xl font-bold text-gray-400">500+ Sites</span>
            <span className="text-gray-300">|</span>
            <span className="text-2xl font-bold text-gray-400">15K+ Students Tracked</span>
            <span className="text-gray-300">|</span>
            <span className="text-2xl font-bold text-gray-400">98% Satisfaction</span>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-20 px-4">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Everything You Need to Understand Your Students</h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              Built specifically for Tutor LMS, with insights that actually help you improve your courses.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            <div className="p-6 rounded-2xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition">
              <div className="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Funnel Analytics</h3>
              <p className="text-gray-600">
                See exactly where students drop off. Identify problem lessons and fix them before more students quit.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition">
              <div className="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Cohort Analysis</h3>
              <p className="text-gray-600">
                Compare different student groups. See how this month&apos;s enrollees perform vs. last month&apos;s.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition">
              <div className="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Quiz Performance</h3>
              <p className="text-gray-600">
                Deep dive into quiz results. Find questions that confuse students and improve your assessments.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition">
              <div className="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Real-time Dashboard</h3>
              <p className="text-gray-600">
                Beautiful charts that update in real-time. No more manual exports or spreadsheet gymnastics.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition">
              <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Export Everything</h3>
              <p className="text-gray-600">
                CSV and JSON exports for all data. Use with Excel, Google Sheets, or your own tools.
              </p>
            </div>

            <div className="p-6 rounded-2xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition">
              <div className="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Role-Based Access</h3>
              <p className="text-gray-600">
                Instructors see their courses, admins see everything. Built-in privacy and security.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Comparison Table */}
      <section id="compare" className="py-20 bg-gray-50 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">TLAT vs. Native Tutor LMS Reports</h2>
            <p className="text-xl text-gray-600">See why course creators upgrade to TLAT</p>
          </div>

          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
            <table className="w-full">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  <th className="text-left py-4 px-6 font-semibold text-gray-900">Feature</th>
                  <th className="text-center py-4 px-6 font-semibold text-gray-500">Native Tutor LMS</th>
                  <th className="text-center py-4 px-6 font-semibold text-indigo-600">TLAT</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                <tr>
                  <td className="py-4 px-6 text-gray-700">Completion funnel analysis</td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr className="bg-gray-50/50">
                  <td className="py-4 px-6 text-gray-700">Cohort comparison</td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr>
                  <td className="py-4 px-6 text-gray-700">Real-time visual charts</td>
                  <td className="py-4 px-6 text-center">
                    <span className="text-gray-400 text-sm">Basic</span>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr className="bg-gray-50/50">
                  <td className="py-4 px-6 text-gray-700">Drop-off point identification</td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr>
                  <td className="py-4 px-6 text-gray-700">Quiz question analytics</td>
                  <td className="py-4 px-6 text-center">
                    <span className="text-gray-400 text-sm">Pass/Fail only</span>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <span className="text-indigo-600 text-sm font-medium">Per-question</span>
                  </td>
                </tr>
                <tr className="bg-gray-50/50">
                  <td className="py-4 px-6 text-gray-700">CSV/JSON export</td>
                  <td className="py-4 px-6 text-center">
                    <span className="text-gray-400 text-sm">Limited</span>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr>
                  <td className="py-4 px-6 text-gray-700">REST API access</td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr className="bg-gray-50/50">
                  <td className="py-4 px-6 text-gray-700">WP-CLI commands</td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <svg className="w-5 h-5 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </td>
                </tr>
                <tr>
                  <td className="py-4 px-6 text-gray-700">Instructor role-based views</td>
                  <td className="py-4 px-6 text-center">
                    <span className="text-gray-400 text-sm">Basic</span>
                  </td>
                  <td className="py-4 px-6 text-center">
                    <span className="text-indigo-600 text-sm font-medium">Advanced + Privacy</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="text-center mt-8">
            <a 
              href="#pricing" 
              onClick={() => trackEvent('pricing_viewed', { source: 'compare' })}
              className="inline-block bg-indigo-500 hover:bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold transition"
            >
              Upgrade Your Analytics →
            </a>
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="py-20 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Up and Running in 5 Minutes</h2>
            <p className="text-xl text-gray-600">No complex setup. No coding required.</p>
          </div>

          <div className="space-y-8">
            <div className="flex items-start gap-6">
              <div className="w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0">1</div>
              <div>
                <h3 className="text-xl font-semibold text-gray-900 mb-2">Install the Plugin</h3>
                <p className="text-gray-600">Upload to WordPress, activate, and enter your license key. That&apos;s it.</p>
              </div>
            </div>
            <div className="flex items-start gap-6">
              <div className="w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0">2</div>
              <div>
                <h3 className="text-xl font-semibold text-gray-900 mb-2">Add the Dashboard</h3>
                <p className="text-gray-600">Use the shortcode <code className="bg-gray-100 px-2 py-1 rounded">[tutor_advanced_stats]</code> anywhere, or access from wp-admin.</p>
              </div>
            </div>
            <div className="flex items-start gap-6">
              <div className="w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0">3</div>
              <div>
                <h3 className="text-xl font-semibold text-gray-900 mb-2">Start Improving</h3>
                <p className="text-gray-600">Instantly see where students struggle. Make data-driven decisions to boost completion rates.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Pricing Section */}
      <section id="pricing" className="py-20 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Simple, Fair Pricing</h2>
            <p className="text-xl text-gray-600">Pay once, use forever. Or subscribe for continuous updates.</p>
          </div>

          <div className="grid md:grid-cols-2 gap-8 max-w-3xl mx-auto">
            {/* Lifetime Deal */}
            <div className="relative border-2 border-indigo-500 rounded-2xl p-8 bg-white shadow-xl">
              <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-indigo-500 text-white px-4 py-1 rounded-full text-sm font-medium">
                Most Popular
              </div>
              <h3 className="text-2xl font-bold text-gray-900 mb-2">Lifetime License</h3>
              <p className="text-gray-600 mb-6">One payment, forever access</p>
              <div className="mb-6">
                <span className="text-5xl font-bold text-gray-900">€99</span>
                <span className="text-gray-500 ml-2">one-time</span>
              </div>
              <ul className="space-y-3 mb-8">
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  All features included
                </li>
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  1 year of updates
                </li>
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Use on 1 site
                </li>
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Email support
                </li>
              </ul>
              <a 
                href="https://buy.stripe.com/dRmaEX2kJ016dGD2yi57W01" 
                onClick={() => trackEvent('checkout_started', { plan: 'lifetime' })}
                className="block w-full bg-indigo-500 hover:bg-indigo-600 text-white text-center py-4 rounded-xl font-semibold transition"
              >
                Get Lifetime Access
              </a>
            </div>

            {/* Annual */}
            <div className="border border-gray-200 rounded-2xl p-8 bg-white">
              <h3 className="text-2xl font-bold text-gray-900 mb-2">Annual License</h3>
              <p className="text-gray-600 mb-6">Stay updated, pay less upfront</p>
              <div className="mb-6">
                <span className="text-5xl font-bold text-gray-900">€15</span>
                <span className="text-gray-500 ml-2">/year</span>
              </div>
              <ul className="space-y-3 mb-8">
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  All features included
                </li>
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Continuous updates
                </li>
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Use on 1 site
                </li>
                <li className="flex items-center gap-2 text-gray-700">
                  <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Email support
                </li>
              </ul>
              <a 
                href="https://buy.stripe.com/9B6bJ1f7vcNS9qn6Oy57W02" 
                onClick={() => trackEvent('checkout_started', { plan: 'annual' })}
                className="block w-full border-2 border-gray-200 hover:border-indigo-500 text-gray-700 hover:text-indigo-600 text-center py-4 rounded-xl font-semibold transition"
              >
                Start Annual Plan
              </a>
            </div>
          </div>

          <p className="text-center text-gray-500 mt-8">
            30-day money-back guarantee. No questions asked.
          </p>
        </div>
      </section>

      {/* FAQ Section */}
      <section id="faq" className="py-20 bg-gray-50 px-4">
        <div className="max-w-3xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
          </div>

          <div className="space-y-6">
            <details className="bg-white rounded-xl p-6 shadow-sm">
              <summary className="font-semibold text-gray-900 cursor-pointer">
                Does this work with Tutor LMS Free?
              </summary>
              <p className="mt-4 text-gray-600">
                Yes! TLAT works with both Tutor LMS Free and Pro. Some features (like certificate tracking) require Pro, but all core analytics work with the free version.
              </p>
            </details>

            <details className="bg-white rounded-xl p-6 shadow-sm">
              <summary className="font-semibold text-gray-900 cursor-pointer">
                Will this slow down my site?
              </summary>
              <p className="mt-4 text-gray-600">
                No. We use aggressive caching and optimized queries. The dashboard loads from cached data, and heavy computations run in the background. Most pages add less than 10ms to load time.
              </p>
            </details>

            <details className="bg-white rounded-xl p-6 shadow-sm">
              <summary className="font-semibold text-gray-900 cursor-pointer">
                Can instructors see all student data?
              </summary>
              <p className="mt-4 text-gray-600">
                Instructors only see data from their own courses. Admins can see everything. Email addresses are masked for non-admin users to protect student privacy.
              </p>
            </details>

            <details className="bg-white rounded-xl p-6 shadow-sm">
              <summary className="font-semibold text-gray-900 cursor-pointer">
                What happens when my license expires?
              </summary>
              <p className="mt-4 text-gray-600">
                You get a 14-day grace period. After that, the plugin continues to work, but you won&apos;t receive updates or support. You can renew anytime to restore access.
              </p>
            </details>

            <details className="bg-white rounded-xl p-6 shadow-sm">
              <summary className="font-semibold text-gray-900 cursor-pointer">
                Do you offer refunds?
              </summary>
              <p className="mt-4 text-gray-600">
                Yes. 30-day money-back guarantee, no questions asked. Just email us and we&apos;ll process your refund within 48 hours.
              </p>
            </details>

            <details className="bg-white rounded-xl p-6 shadow-sm">
              <summary className="font-semibold text-gray-900 cursor-pointer">
                Can I use this on multiple sites?
              </summary>
              <p className="mt-4 text-gray-600">
                Each license is for 1 site. Need more? Contact us for agency pricing — we offer discounts for 5+ sites.
              </p>
            </details>
          </div>
        </div>
      </section>

      {/* Final CTA */}
      <section className="py-20 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-4xl font-bold text-gray-900 mb-6">
            Stop Losing Students. Start Understanding Them.
          </h2>
          <p className="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
            Join 500+ course creators who use TLAT to improve their courses and boost completion rates.
          </p>
          <a 
            href="#pricing" 
            onClick={() => trackEvent('pricing_viewed', { source: 'final_cta' })}
            className="inline-block bg-indigo-500 hover:bg-indigo-600 text-white px-10 py-4 rounded-xl font-semibold text-lg transition shadow-lg shadow-indigo-500/25"
          >
            Get Started Today — €99 Lifetime
          </a>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-gray-100 py-12 px-4">
        <div className="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center">
              <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <span className="font-bold">Tutor LMS Advanced Tracking</span>
          </div>
          <div className="flex gap-8 text-gray-600 text-sm">
            <a href="mailto:support@tutor-tracking.com" className="hover:text-gray-900 transition">Support</a>
            <a href="/privacy" className="hover:text-gray-900 transition">Privacy</a>
            <a href="/terms" className="hover:text-gray-900 transition">Terms</a>
            <a href="https://docs.tutor-tracking.com" className="hover:text-gray-900 transition">Docs</a>
          </div>
          <p className="text-gray-500 text-sm">© 2026 Mahope. All rights reserved.</p>
        </div>
      </footer>
    </div>
  )
}
