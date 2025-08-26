import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    refer_by: '',
    side: 'L',
    spillover: true,
  });

  useEffect(() => {
    try {
      const params = new URLSearchParams(window.location.search);
      const ref = params.get('refer_by');
      if (ref && !data.refer_by) setData('refer_by', ref);
    } catch (_) {}
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submit = (e) => {
    e.preventDefault();
    post(route('register'), {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <>
      <Head title="Create your account" />
      {/* Background */}
      <div className="min-h-screen w-full bg-gradient-to-br from-indigo-50 via-white to-emerald-50">
        <div className="mx-auto max-w-6xl px-4 py-10">
          {/* Card */}
          <div className="grid grid-cols-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl md:grid-cols-2">
            {/* Left: Brand / Intro */}
            <div className="relative hidden md:block">
              <div className="absolute inset-0 bg-gradient-to-br from-indigo-600 via-sky-600 to-emerald-500 opacity-90" />
              <div className="relative z-10 flex h-full flex-col justify-between p-10 text-white">
                <div>
                  {/* <img src="/your-logo.svg" alt="Brand" className="h-8" /> */}
                  <h1 className="mt-6 text-3xl font-extrabold leading-tight">
                    Create your account
                  </h1>
                  <p className="mt-2 text-white/90">
                    Join your team and start tracking purchases, referrals, and payouts from a single dashboard.
                  </p>
                </div>
                <ul className="space-y-3 text-sm text-white/90">
                  <li>• Secure sign up</li>
                  <li>• Sponsor referral support</li>
                  <li>• Left / Right placement control</li>
                </ul>
              </div>
              <div className="pointer-events-none absolute -left-12 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl" />
              <div className="pointer-events-none absolute -right-10 -bottom-10 h-44 w-44 rounded-full bg-white/15 blur-2xl" />
            </div>

            {/* Right: Form */}
            <div className="p-6 sm:p-10">
              <form onSubmit={submit} className="space-y-5">
                {/* Header (no Laravel branding) */}
                <div className="mb-2 md:hidden">
                  <h2 className="text-2xl font-bold text-slate-800">Create your account</h2>
                  <p className="text-sm text-slate-500">It only takes a minute.</p>
                </div>

                {/* Name */}
                <div>
                  <InputLabel htmlFor="name" value="Full name" />
                  <TextInput
                    id="name"
                    name="name"
                    value={data.name}
                    className="mt-1 block w-full"
                    autoComplete="name"
                    isFocused={true}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                  />
                  <InputError message={errors.name} className="mt-2" />
                </div>

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
                    onChange={(e) => setData('email', e.target.value)}
                    required
                  />
                  <InputError message={errors.email} className="mt-2" />
                </div>

                {/* Passwords (grid) */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                      id="password"
                      type="password"
                      name="password"
                      value={data.password}
                      className="mt-1 block w-full"
                      autoComplete="new-password"
                      onChange={(e) => setData('password', e.target.value)}
                      required
                    />
                    <InputError message={errors.password} className="mt-2" />
                  </div>
                  <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                    <TextInput
                      id="password_confirmation"
                      type="password"
                      name="password_confirmation"
                      value={data.password_confirmation}
                      className="mt-1 block w-full"
                      autoComplete="new-password"
                      onChange={(e) => setData('password_confirmation', e.target.value)}
                      required
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                  </div>
                </div>

                {/* Sponsor / Placement */}
                <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {/* Referral ID */}
                    <div>
                      <InputLabel htmlFor="refer_by" value="Referral ID (Sponsor code)" />
                      <TextInput
                        id="refer_by"
                        name="refer_by"
                        value={data.refer_by}
                        className="mt-1 block w-full"
                        placeholder="e.g., ROOT1234"
                        onChange={(e) => setData('refer_by', e.target.value.trim())}
                        required
                      />
                      <p className="mt-1 text-xs text-gray-500">
                        URL me ?refer_by=CODE ho to ye field auto-fill ho jayega.
                      </p>
                      <InputError message={errors.refer_by} className="mt-2" />
                    </div>

                    {/* Side segmented control */}
                    <div>
                      <InputLabel value="Placement side" />
                      <div className="mt-2 grid grid-cols-2 overflow-hidden rounded-lg border border-slate-200">
                        <button
                          type="button"
                          onClick={() => setData('side', 'L')}
                          className={`py-2 text-sm font-medium ${
                            data.side === 'L' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'
                          }`}
                        >
                          Left
                        </button>
                        <button
                          type="button"
                          onClick={() => setData('side', 'R')}
                          className={`py-2 text-sm font-medium ${
                            data.side === 'R' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'
                          }`}
                        >
                          Right
                        </button>
                      </div>
                      <InputError message={errors.side} className="mt-2" />
                    </div>
                  </div>

                  {/* Spillover */}
                  <label className="mt-3 flex cursor-pointer items-center gap-2">
                    <input
                      id="spillover"
                      type="checkbox"
                      checked={!!data.spillover}
                      onChange={(e) => setData('spillover', e.target.checked)}
                      className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                    />
                    <span className="text-sm text-gray-700">
                      If chosen side is occupied, place in first free slot in the same branch (spillover)
                    </span>
                  </label>
                  <InputError message={errors.spillover} className="mt-2" />
                </div>

                {/* Actions */}
                <div className="flex items-center justify-between pt-2">
                  <Link
                    href={route('login')}
                    className="text-sm font-medium text-slate-600 underline hover:text-slate-900"
                  >
                    Already registered?
                  </Link>
                  <PrimaryButton className="ms-4" disabled={processing}>
                    {processing ? 'Registering…' : 'Register'}
                  </PrimaryButton>
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
    </>
  );
}
