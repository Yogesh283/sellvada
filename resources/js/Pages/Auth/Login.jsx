import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const [showPw, setShowPw] = useState(false);

  const submit = (e) => {
    e.preventDefault();
    post(route('login'), { onFinish: () => reset('password') });
  };

  return (
    <GuestLayout>
      <Head title="Log in" />

      {/* Background */}
      <div className="min-h-screen w-full bg-gradient-to-br from-indigo-50 via-white to-emerald-50">
        <div className="mx-auto max-w-6xl px-4 py-10">
          {/* Card */}
          <div className="grid grid-cols-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl md:grid-cols-2">

            {/* Left side: vibe / copy (no Laravel logo) */}
            <div className="relative hidden md:block">
              <div className="absolute inset-0 bg-gradient-to-br from-indigo-600 via-sky-600 to-emerald-500 opacity-90" />
              <div className="relative z-10 flex h-full flex-col justify-between p-10 text-white">
                <div>
                  {/* <img src="/your-logo.svg" alt="Brand" className="h-8" /> */}
                  <h1 className="mt-6 text-3xl font-extrabold leading-tight">
                    Welcome back
                  </h1>
                  <p className="mt-2 text-white/90">
                    Sign in to access your dashboard, track purchases, and manage your team.
                  </p>
                </div>
                <ul className="space-y-3 text-sm text-white/90">
                  <li>• Secure login</li>
                  <li>• Two-column clean UI</li>
                  <li>• Keyboard and screen-reader friendly</li>
                </ul>
              </div>
              <div className="pointer-events-none absolute -left-12 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl" />
              <div className="pointer-events-none absolute -right-10 -bottom-10 h-44 w-44 rounded-full bg-white/15 blur-2xl" />
            </div>

            {/* Right side: form */}
            <div className="p-6 sm:p-10">
              {/* Header (mobile) */}
              <div className="mb-4 md:hidden">
                <h2 className="text-2xl font-bold text-slate-800">Welcome back</h2>
                <p className="text-sm text-slate-500">Please sign in to continue.</p>
              </div>

              {status && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                  {status}
                </div>
              )}

              <form onSubmit={submit} className="space-y-5">
                {/* Email */}
                <div>
                  <InputLabel htmlFor="email" value="Email" />
                  <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    autoComplete="username"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                  />
                  <InputError message={errors.email} className="mt-2" />
                </div>

                {/* Password + show/hide */}
                <div>
                  <InputLabel htmlFor="password" value="Password" />
                  <div className="relative">
                    <TextInput
                      id="password"
                      type={showPw ? 'text' : 'password'}
                      name="password"
                      value={data.password}
                      className="mt-1 block w-full pr-20"
                      autoComplete="current-password"
                      onChange={(e) => setData('password', e.target.value)}
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowPw((s) => !s)}
                      className="absolute inset-y-0 right-0 my-1 mr-1 rounded-md bg-slate-100 px-3 text-sm font-medium text-slate-700 hover:bg-slate-200"
                      aria-label={showPw ? 'Hide password' : 'Show password'}
                    >
                      {showPw ? 'Hide' : 'Show'}
                    </button>
                  </div>
                  <InputError message={errors.password} className="mt-2" />
                </div>

                {/* Remember + Forgot */}
                <div className="flex items-center justify-between">
                  <label className="flex select-none items-center">
                    <Checkbox
                      name="remember"
                      checked={data.remember}
                      onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <span className="ms-2 text-sm text-gray-600">Remember me</span>
                  </label>

                  {canResetPassword && (
                    <Link
                      href={route('password.request')}
                      className="text-sm font-medium text-sky-700 underline hover:text-sky-900"
                    >
                      Forgot your password?
                    </Link>
                  )}
                </div>

                {/* Submit */}
                <div className="pt-1 flex items-center justify-end">
                  <PrimaryButton className="ms-4" disabled={processing}>
                    {processing ? 'Logging in…' : 'Log in'}
                  </PrimaryButton>
                </div>

                {/* Divider + link to register (optional) */}
                <div className="pt-4 text-center text-sm text-slate-500">
                  Don’t have an account?{' '}
                  <Link href={route('register')} className="font-medium text-sky-700 underline hover:text-sky-900">
                    Create one
                  </Link>
                </div>
              </form>
            </div>
          </div>

          {/* Footer (no Laravel text) */}
          <div className="mt-6 text-center text-xs text-slate-500">
            © {new Date().getFullYear()} Your Company. All rights reserved.
          </div>
        </div>
      </div>
    </GuestLayout>
  );
}
