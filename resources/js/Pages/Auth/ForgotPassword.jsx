// resources/js/Pages/Auth/ForgotPassword.jsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post(route('password.email'));
  };

  return (
    <GuestLayout>
      <Head title="Forgot Password" />

      {/* Background */}
      <div className="min-h-screen w-full bg-gradient-to-br from-indigo-50 via-white to-emerald-50">
        <div className="mx-auto max-w-6xl px-4 py-10">
          {/* Card */}
          <div className="grid grid-cols-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl md:grid-cols-2">

            {/* Left: accent panel (no Laravel logo) */}
            <div className="relative hidden md:block">
              <div className="absolute inset-0 bg-gradient-to-br from-indigo-600 via-sky-600 to-emerald-500 opacity-90" />
              <div className="relative z-10 flex h-full flex-col justify-between p-10 text-white">
                <div>
                  {/* <img src="/your-logo.svg" alt="Brand" className="h-8" /> */}
                  <h1 className="mt-6 text-3xl font-extrabold leading-tight">
                    Reset your password
                  </h1>
                  <p className="mt-2 text-white/90">
                    Enter your email and we’ll send you a secure link to set a new password.
                  </p>
                </div>
                <ul className="space-y-3 text-sm text-white/90">
                  <li>• Fast, secure reset</li>
                  <li>• Works on mobile & desktop</li>
                  <li>• Clean, brand-free UI</li>
                </ul>
              </div>
              <div className="pointer-events-none absolute -left-12 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl" />
              <div className="pointer-events-none absolute -right-10 -bottom-10 h-44 w-44 rounded-full bg-white/15 blur-2xl" />
            </div>

            {/* Right: form */}
            <div className="p-6 sm:p-10">
              {/* Header (mobile) */}
              <div className="mb-4 md:hidden">
                <h2 className="text-2xl font-bold text-slate-800">Forgot Password</h2>
                <p className="text-sm text-slate-500">
                  We’ll email you a password reset link.
                </p>
              </div>

              {status && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                  {status}
                </div>
              )}

              <form onSubmit={submit} className="space-y-5">
                <div>
                  <InputLabel htmlFor="email" value="Email address" />
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

                <div className="pt-1 flex items-center justify-end">
                  <PrimaryButton className="ms-4" disabled={processing}>
                    {processing ? 'Sending…' : 'Email Password Reset Link'}
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
    </GuestLayout>
  );
}
