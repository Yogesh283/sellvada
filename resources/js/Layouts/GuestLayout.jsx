import React from 'react';

export default function GuestLayout({ children }) {
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Optional top bar for your brand (empty for now) */}
      <header className="mx-auto max-w-6xl px-4 py-4">
        {/* <img src="/your-logo.svg" alt="Brand" className="h-7" /> */}
      </header>
      <main>{children}</main>
    </div>
  );
}
