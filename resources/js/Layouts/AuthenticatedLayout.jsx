import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
  const user = usePage().props?.auth?.user ?? null;
  const [open, setOpen] = useState(false);

  const is = (name) => (typeof route !== 'undefined' ? route().current(name) : false);

  return (
    <div className="min-h-screen bg-gray-50 text-black">
      {/* NAVBAR */}
      <nav className="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex h-16 items-center justify-between">
            {/* Left: Logo + links */}
            <div className="flex items-center gap-7">
              <Link href="/" className="flex items-center gap-2 shrink-0">
                {/* Use your logo image or <ApplicationLogo /> */}
                <img src="/image/11111.png" alt="Logo" className="h-10 w-auto" />
                <span className="hidden sm:block font-semibold text-lg tracking-tight">
                  Cellvada
                </span>
              </Link>

              <div className="hidden sm:flex items-center gap-6 font-medium">
                <NavLink href={route('dashboard')} active={is('dashboard')} className="text-black">
                  Dashboard
                </NavLink>

                {/* Adjust if you actually have a named 'home' route */}
                <NavLink href="/" active={is('home')} className="text-black">
                  Home
                </NavLink>

                {/* Deposit uses the named route so 'active' can match */}
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
            <div className="hidden sm:flex items-center">
              {user ? (
                <Dropdown>
                  <Dropdown.Trigger>
                    <span className="inline-flex rounded-md">
                      <button
                        type="button"
                        className="inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none"
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
                    </span>
                  </Dropdown.Trigger>

                  <Dropdown.Content>
                    <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                    <Dropdown.Link href={route('logout')} method="post" as="button">
                      Logout
                    </Dropdown.Link>
                  </Dropdown.Content>
                </Dropdown>
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
          <div className="sm:hidden border-t border-gray-200 bg-white">
            <div className="space-y-1 px-2 pt-2 pb-3">
              <ResponsiveNavLink href={route('dashboard')} active={is('dashboard')}>
                Dashboard
              </ResponsiveNavLink>
              <ResponsiveNavLink href="/" active={is('home')}>
                Home
              </ResponsiveNavLink>
              <ResponsiveNavLink href={route('wallet.deposit')} active={is('wallet.deposit')}>
                Deposit
              </ResponsiveNavLink>
            </div>

            <div className="border-t border-gray-200 px-4 py-3">
              {user ? (
                <>
                  <div className="text-base font-semibold">{user.name}</div>
                  <div className="text-sm text-gray-500">{user.email}</div>
                </>
              ) : (
                <div className="mt-2 flex gap-3">
                  <ResponsiveNavLink href={route('login')}>Login</ResponsiveNavLink>
                  <ResponsiveNavLink href={route('register')}>Register</ResponsiveNavLink>
                </div>
              )}
            </div>

            {user && (
              <div className="space-y-1 px-2 py-2 border-t border-gray-200">
                <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                <ResponsiveNavLink method="post" href={route('logout')} as="button">
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
