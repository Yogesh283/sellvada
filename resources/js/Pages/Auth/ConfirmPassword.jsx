// resources/js/Pages/Auth/ConfirmPassword.jsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ConfirmPassword() {
  const { data, setData, post, processing, errors, reset } = useForm({
    password: '',
  });

  const [showPw, setShowPw] = useState(false);

  const submit = (e) => {
    e.preventDefault();
    post(route('password.confirm'), {
      onFinish: () => reset('password'),
    });
  };

  return (
    <GuestLayout>
      <Head title="Confirm Password" />

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
                    Extra security check
                  </h1>
                  <p className="mt-2 text-white/90">
                    This is a secure area. Please confirm your password to continue.
                  </p>
                </div>
                <ul className="space-y-3 text-sm text-white/90">
                  <li>• Protects sensitive actions</li>
                  <li>• Works with your account password</li>
                  <li>• No branding, clean UI</li>
                </ul>
              </div>
              <div className="pointer-events-none absolute -left-12 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl" />
              <div className="pointer-events-none absolute -right-10 -bottom-10 h-44 w-44 rounded-full bg-white/15 blur-2xl" />
            </div>

            {/* Right: form */}
            <div className="p-6 sm:p-10">
              {/* Header (mobile) */}
              <div className="mb-4 md:hidden">
                <h2 className="text-2xl font-bold text-slate-800">Confirm Password</h2>
                <p className="text-sm text-slate-500">
                  Please confirm your password before continuing.
                </p>
              </div>

              <form onSubmit={submit} className="space-y-5">
                <div>
                  <InputLabel htmlFor="password" value="Password" />
                  <div className="relative">
                    <TextInput
                      id="password"
                      type={showPw ? 'text' : 'password'}
                      name="password"
                      value={data.password}
                      className="mt-1 block w-full pr-20"
                      isFocused={true}
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

                <div className="pt-1 flex items-center justify-end">
                  <PrimaryButton className="ms-4" disabled={processing}>
                    {processing ? 'Confirming…' : 'Confirm'}
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
