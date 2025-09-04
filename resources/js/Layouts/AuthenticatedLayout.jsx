import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
  const user = usePage().props?.auth?.user ?? null;
  const [open, setOpen] = useState(false);           // mobile sheet
  const [panelOpen, setPanelOpen] = useState(false); // desktop panel

  const is = (name) => (typeof route !== 'undefined' ? route().current(name) : false);

  // Safe href for Repurchase page (named route or plain path)
  const repurchaseHref = (() => {
    try { return route('repurchase.index'); } catch { return '/repurchase'; }
  })();

  return (
    <div className="min-h-screen bg-gray-50 text-black">
      {/* NAVBAR */}
      <nav className="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex h-16 items-center justify-between">
            {/* Left: Logo + links */}
            <div className="flex items-center gap-7">
              <Link href="/" className="flex items-center gap-2 shrink-0">
                <img src="/image/11111.png" alt="Logo" className="h-10 w-auto" />
                <span className="hidden sm:block font-semibold text-lg tracking-tight">
                  Cellveda
                </span>
              </Link>

              <div className="hidden sm:flex items-center gap-6 font-medium">
                <NavLink href={route('dashboard')} active={is('dashboard')} className="text-black">
                  Dashboard
                </NavLink>
              


                <NavLink href="/" active={is('home')} className="text-black">
                  Products
                </NavLink>

                {/* ✅ Repurchase (new) */}
                <NavLink
                  href={repurchaseHref}
                  active={is('repurchase.index') || is('repurchase')}
                  className="text-black"
                >
                  Repurchase
                </NavLink>

                <NavLink
                  href={route('wallet.deposit')}
                  active={is('wallet.deposit')}
                  className="text-black"
                >
                  Deposit
                </NavLink>
                <NavLink
                  href={route('wallet.withdraw')}
                  active={is('wallet.withdraw')}
                  className="text-black"
                >
                  Withdraw
                </NavLink>
              </div>
            </div>

            {/* Right: user / guest actions (desktop) */}
            <div className="hidden sm:flex items-center relative">
              {user ? (
                <>
                  <button
                    type="button"
                    onClick={() => setPanelOpen((v) => !v)}
                    className="inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none"
                    aria-haspopup="true"
                    aria-expanded={panelOpen}
                  >
                    {user.name}
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path
                        fillRule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clipRule="evenodd"
                      />
                    </svg>
                  </button>

                  {/* Desktop panel */}
                  {panelOpen && (
                    <div
                      className="absolute right-0 top-12 w-80 rounded-xl border border-gray-200 bg-white shadow-xl"
                      onMouseLeave={() => setPanelOpen(false)}
                    >
                      <div className="px-4 py-4 border-b border-gray-200">
                        <div className="text-lg font-bold text-gray-900">{user.name}</div>
                        <div className="text-sm text-gray-500">{user.email}</div>
                        <div className="text-sm text-gray-600 mt-1">User ID: {user.id}</div>
                      </div>

                      <div className="space-y-1 px-2 pt-2 pb-3">
                        <DesktopMenuLink href={route('dashboard')} active={is('dashboard')} onClick={() => setPanelOpen(false)}>
                          Dashboard
                        </DesktopMenuLink>

                        <DesktopMenuLink href="/" active={is('home')} onClick={() => setPanelOpen(false)}>
                          Products
                        </DesktopMenuLink>

                        {/* Cart link (if you use it) */}
                        <DesktopMenuLink href="/card" active={is && is('card')} onClick={() => setPanelOpen(false)}>
                          Cart
                        </DesktopMenuLink>

                        {/* ✅ Repurchase (new) */}
                        <DesktopMenuLink
                          href={repurchaseHref}
                          active={is('repurchase.index') || is('repurchase')}
                          onClick={() => setPanelOpen(false)}
                        >
                          Repurchase
                        </DesktopMenuLink>

                        <DesktopMenuLink href={route('wallet.deposit')} active={is('wallet.deposit')} onClick={() => setPanelOpen(false)}>
                          Deposit
                        </DesktopMenuLink>
                        <DesktopMenuLink href={route('wallet.withdraw')} active={is('wallet.withdraw')} onClick={() => setPanelOpen(false)}>
                          Withdrawal
                        </DesktopMenuLink>
                      </div>

                      <div className="border-t border-gray-200 px-4 py-3">
                        <Link
                          href={route('profile.edit')}
                          className="block rounded-md px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                          onClick={() => setPanelOpen(false)}
                        >
                          Profile
                        </Link>
                      </div>

                      <div className="border-t border-gray-200 px-4 py-3">
                        <Link
                          method="post"
                          href={route('logout')}
                          as="button"
                          className="w-full text-left rounded-md px-2 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                        >
                          Logout
                        </Link>
                      </div>
                    </div>
                  )}
                </>
              ) : (
                <div className="flex items-center gap-3">
                  <Link
                    href={route('login')}
                    className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50"
                  >
                    Login
                  </Link>
                  <Link
                    href={route('register')}
                    className="rounded-md bg-green-500 px-3 py-2 text-sm font-semibold text-white hover:bg-green-400"
                  >
                    Register
                  </Link>
                </div>
              )}
            </div>

            {/* ✅ Mobile: show username before hamburger */}
            {user && (
              <div className="sm:hidden text-sm font-medium text-gray-700 mr-2 truncate max-w-[120px]">
                {user.name}
              </div>
            )}

            {/* Mobile: hamburger */}
            <button
              onClick={() => setOpen((v) => !v)}
              className="sm:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100 focus:outline-none"
              aria-label="Toggle navigation"
            >
              {!open ? (
                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              ) : (
                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              )}
            </button>
          </div>
        </div>

        {/* Mobile menu */}
        {open && (
          <div className="sm:hidden bg-white border-t border-gray-200">
            <div className="px-4 py-4 border-b border-gray-200">
              {user && (
                <>
                  <div className="text-lg font-bold text-gray-900">{user.name}</div>
                  <div className="text-sm text-gray-500">{user.email}</div>
                  <div className="text-sm text-gray-600 mt-1">User ID: {user.id}</div>
                </>
              )}
            </div>

            <div className="space-y-1 px-2 pt-2 pb-3">
              <ResponsiveNavLink href={route('dashboard')} active={is('dashboard')}>
                Dashboard
              </ResponsiveNavLink>
              <ResponsiveNavLink href="/" active={is('home')}>
                Products
              </ResponsiveNavLink>

              {/* ✅ Repurchase (new) */}
              <ResponsiveNavLink href={repurchaseHref} active={is('repurchase.index') || is('repurchase')}>
                Repurchase
              </ResponsiveNavLink>

              <ResponsiveNavLink href={route('wallet.deposit')} active={is('wallet.deposit')}>
                Deposit
              </ResponsiveNavLink>
              <ResponsiveNavLink href={route('wallet.withdraw')} active={is('wallet.withdraw')}>
                Withdrawal
              </ResponsiveNavLink>
            </div>

            <div className="border-t border-gray-200 px-4 py-3">
              <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
            </div>

            {user && (
              <div className="border-t border-gray-200 px-4 py-3">
                <ResponsiveNavLink
                  method="post"
                  href={route('logout')}
                  as="button"
                  className="text-red-600 font-semibold"
                >
                  Logout
                </ResponsiveNavLink>
              </div>
            )}
          </div>
        )}
      </nav>

      {/* Optional page header */}
      {header && (
        <header className="bg-white shadow-sm">
          <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div>
        </header>
      )}

      {/* Page content */}
      <main>{children}</main>
    </div>
  );
}

/* --- helpers for desktop panel links (same visual as ResponsiveNavLink) --- */
function DesktopMenuLink({ href, active, children, onClick }) {
  const base = 'block rounded-md px-3 py-2 text-sm font-medium transition';
  const activeCls = 'bg-indigo-50 text-indigo-700';
  const idleCls = 'text-gray-700 hover:bg-gray-50';
  return (
    <Link href={href} className={`${base} ${active ? activeCls : idleCls}`} onClick={onClick}>
      {children}
    </Link>
  );
}
