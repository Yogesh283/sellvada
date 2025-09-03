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
    remember: true,
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
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-emerald-50 px-4">
        {/* Card */}
        <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-xl">
          
          {/* Logo on top */}
          <div className="mb-6 text-center">
<img src="/image/11111.png" className="mx-auto h-23 w-20  " />
          </div>

          {/* Header */}
          <div className="mb-6 text-center">
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

            {/* Password */}
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

            
            </div>

            {/* Submit */}
            <div className="pt-2">
              <PrimaryButton className="w-full justify-center" disabled={processing}>
                {processing ? 'Logging in…' : 'Log in'}
              </PrimaryButton>
            </div>
          </form>

          {/* Register link */}
          <div className="mt-6 text-center text-sm text-slate-500">
            Don’t have an account?{' '}
            <Link href={route('register')} className="font-medium text-sky-700 underline hover:text-sky-900">
              Create one
            </Link>
          </div>
        </div>
      </div>
    </GuestLayout>
  );
}
