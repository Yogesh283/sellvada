import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function AuthenticatedLayout({ header, children }) {
  const user = usePage().props?.auth?.user ?? null;
  const [open, setOpen] = useState(false);
  const [panelOpen, setPanelOpen] = useState(false);

  const [balance, setBalance] = useState(0.0);
  const [balanceLoading, setBalanceLoading] = useState(false);

  const is = (name) => (typeof route !== 'undefined' ? route().current(name) : false);

  const repurchaseHref = (() => { try { return route('repurchase.index'); } catch { return '/repurchase'; } })();
  const ordersHref = (() => { try { return route('orders.index'); } catch { return '/orders'; } })();

  useEffect(() => {
    if (!user) return;
    let mounted = true;
    const fetchBalance = async () => {
      try {
        setBalanceLoading(true);
        const res = await axios.get('/p2p/balance', { headers: { Accept: 'application/json' } });
        if (!mounted) return;
        if (res?.data?.balance !== undefined) setBalance(Number(res.data.balance));
      } catch (err) {
        console.error('Failed to fetch balance', err);
      } finally {
        if (mounted) setBalanceLoading(false);
      }
    };
    fetchBalance();
    const iv = setInterval(fetchBalance, 60_000);
    return () => { mounted = false; clearInterval(iv); };
  }, [user]);

  return (
    <div className="min-h-screen bg-gray-50 text-black">
      <nav className="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex h-16 items-center justify-between">
            {/* Left: Logo + links */}
            <div className="flex items-center gap-7">
              <Link href="/" className="flex items-center gap-2 shrink-0">
                <img src="/image/11111.png" alt="Logo" className="h-10 w-auto" />
                <span className="hidden sm:block font-semibold text-lg tracking-tight">Cellveda</span>
              </Link>

              <div className="hidden sm:flex items-center gap-6 font-medium">
                <NavLink href={route('dashboard')} active={is('dashboard')}>Dashboard</NavLink>
                <NavLink href="/" active={is('home')}>Products</NavLink>
                <NavLink href={ordersHref} active={is('orders.index') || is('orders')}>Orders</NavLink>
                <NavLink href={repurchaseHref} active={is('repurchase.index') || is('repurchase')}>Repurchase</NavLink>
                <NavLink href={route('wallet.deposit')} active={is('wallet.deposit')}>Deposit</NavLink>
                <NavLink href={route('wallet.withdraw')} active={is('wallet.withdraw')}>Withdraw</NavLink>
                <NavLink href={route('payouts.index')} active={is('payouts.index') || is('payouts')}>Payouts</NavLink>
                <NavLink href={route('p2p.transfer.page')} active={is('p2p.transfer.page')}>P2P Transfer</NavLink>
              </div>
            </div>

            {/* Right: desktop + mobile actions */}
            <div className="flex items-center gap-3">
              {user ? (
                <>
                  {/* Desktop dropdown toggle */}
                  <div className="hidden sm:flex relative">
                    <button
                      type="button"
                      onClick={() => setPanelOpen((v) => !v)}
                      className="inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
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
                    {panelOpen && (
                      <div className="absolute right-0 top-12 w-44 rounded-lg border border-gray-200 bg-white shadow-lg text-sm">
                        <div className="px-3 py-3 border-b border-gray-200">
                          <div className="text-sm font-semibold">{user.name}</div>
                          <div className="text-xs text-gray-500">{user.email}</div>
                          <div className="text-xs text-gray-600 mt-1">ID: {user.id}</div>
                          <div className="text-xs text-gray-700 mt-2">
                            Balance: {balanceLoading ? "…" : Number(balance || 0).toFixed(2)}
                          </div>
                        </div>
                        <div className="px-2 py-2 border-t border-gray-200">
                          <Link href={route('profile.edit')} className="block text-xs py-1">Profile</Link>
                          <Link method="post" href={route('logout')} as="button" className="w-full text-left text-xs py-1 text-red-600">Logout</Link>
                        </div>
                      </div>
                    )}
                  </div>
                </>
              ) : (
                <>
                  {/* Guest: show login/register on both desktop & mobile */}
                  <Link href={route('login')} className="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50">Login</Link>
                  <Link href={route('register')} className="rounded-md bg-green-500 px-3 py-2 text-sm font-semibold text-white hover:bg-green-400">Register</Link>
                </>
              )}

              {/* Mobile hamburger — only for logged-in users */}
              {user && (
                <button
                  onClick={() => setOpen((v) => !v)}
                  className="sm:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100"
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
              )}
            </div>
          </div>
        </div>

        {/* Mobile menu — only when user is logged in */}
        {open && user && (
          <div className="sm:hidden bg-white border-t border-gray-200">
            <div className="px-4 py-4 border-b border-gray-200">
              <div className="text-lg font-bold">{user.name}</div>
              <div className="text-sm text-gray-500">{user.email}</div>
              <div className="text-sm text-gray-600 mt-1">User ID: {user.id}</div>
            </div>
            <div className="space-y-1 px-2 pt-2 pb-3">
              <ResponsiveNavLink href={route('dashboard')} active={is('dashboard')}>Dashboard</ResponsiveNavLink>
              <ResponsiveNavLink href="/" active={is('home')}>Products</ResponsiveNavLink>
              <ResponsiveNavLink href={ordersHref} active={is('orders.index')}>Orders</ResponsiveNavLink>
              <ResponsiveNavLink href={repurchaseHref} active={is('repurchase.index')}>Repurchase</ResponsiveNavLink>
              <ResponsiveNavLink href={route('wallet.deposit')} active={is('wallet.deposit')}>Deposit</ResponsiveNavLink>
              <ResponsiveNavLink href={route('wallet.withdraw')} active={is('wallet.withdraw')}>Withdrawal</ResponsiveNavLink>
              <ResponsiveNavLink href={route('payouts.index')} active={is('payouts.index')}>Payouts</ResponsiveNavLink>
              <ResponsiveNavLink href={route('p2p.transfer.page')} active={is('p2p.transfer.page')}>P2P Transfer</ResponsiveNavLink>
            </div>
            <div className="border-t border-gray-200 px-4 py-3">
              <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
              <ResponsiveNavLink method="post" href={route('logout')} as="button" className="text-red-600 font-semibold">Logout</ResponsiveNavLink>
            </div>
          </div>
        )}
      </nav>

      {header && <header className="bg-white shadow-sm"><div className="mx-auto max-w-7xl px-4 py-6">{header}</div></header>}
      <main>{children}</main>
    </div>
  );
}

function DesktopMenuLinkCompact({ href, active, children, onClick }) {
  return (
    <Link
      href={href}
      className={`block rounded-md px-2 py-1 text-xs ${active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'}`}
      onClick={onClick}
    >
      {children}
    </Link>
  );
}
