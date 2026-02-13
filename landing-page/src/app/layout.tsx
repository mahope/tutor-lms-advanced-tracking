import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Tutor LMS Advanced Tracking - Analytics for Your Online Courses',
  description: 'Get actionable insights into your Tutor LMS courses. Track student progress, analyze completion rates, and improve your course content with beautiful dashboards.',
  keywords: ['Tutor LMS', 'WordPress', 'course analytics', 'LMS tracking', 'student progress', 'completion rates'],
  openGraph: {
    title: 'Tutor LMS Advanced Tracking',
    description: 'Advanced analytics for Tutor LMS courses',
    type: 'website',
    url: 'https://tutor-tracking.com',
  },
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en">
      <body className="antialiased">{children}</body>
    </html>
  )
}
