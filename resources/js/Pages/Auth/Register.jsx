import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PhoneInput from 'react-phone-input-2';
import 'react-phone-input-2/lib/style.css';

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '', email: '', phone: '', password: '', password_confirmation: '',
    refer_by: '', side: 'L', spillover: true,
  });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  useEffect(() => {
    try {
      const ref = new URLSearchParams(window.location.search).get('refer_by');
      if (ref && !data.refer_by) setData('refer_by', ref);
    } catch (_) {}
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submit = (e) => {
    e.preventDefault();
    post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
  };

  return (
    <>
      <Head title="Create your account" />
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-emerald-50 px-4">
        <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-xl">
          <div className="mb-6 text-center"><img src="/image/11111.png" alt="Logo" className="mx-auto h-14 w-auto" /></div>
          <div className="mb-6 text-center">
            <h2 className="text-2xl font-bold text-slate-800">Create your account</h2>
            <p className="text-sm text-slate-500">It only takes a minute.</p>
          </div>

          <form onSubmit={submit} className="space-y-5" noValidate>
            <div>
              <InputLabel htmlFor="name" value="Full name" />
              <TextInput id="name" name="name" value={data.name} className="mt-1 block w-full" autoComplete="name" isFocused onChange={e=>setData('name',e.target.value)} required />
              <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
              <InputLabel htmlFor="email" value="Email" />
              <TextInput id="email" type="email" name="email" value={data.email} className="mt-1 block w-full" autoComplete="username" onChange={e=>setData('email',e.target.value)} required />
              <InputError message={errors.email} className="mt-2" />
            </div>

            <div>
              <InputLabel htmlFor="phone" value="Phone number" />
              <PhoneInput
                country="in"
                onlyCountries={['in','us','gb','ca','au','ae','de','fr','cn','jp']}
                value={data.phone}
                onChange={phone => setData('phone', phone.startsWith('+') ? phone : `+${phone}`)}
                inputClass="!w-full !h-11"
                containerClass="!w-full"
              />
              <InputError message={errors.phone} className="mt-2" />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="relative">
                <InputLabel htmlFor="password" value="Password" />
                <TextInput id="password" type={showPassword ? 'text' : 'password'} name="password" value={data.password} className="mt-1 block w-full pr-10" autoComplete="new-password" onChange={e=>setData('password',e.target.value)} required />
                <button type="button" onClick={()=>setShowPassword(p=>!p)} className="absolute right-3 top-9 text-gray-500 hover:text-gray-700">{showPassword ? '🙈' : '👁️'}</button>
                <InputError message={errors.password} className="mt-2" />
              </div>

              <div className="relative">
                <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                <TextInput id="password_confirmation" type={showConfirm ? 'text' : 'password'} name="password_confirmation" value={data.password_confirmation} className="mt-1 block w-full pr-10" autoComplete="new-password" onChange={e=>setData('password_confirmation',e.target.value)} required />
                <button type="button" onClick={()=>setShowConfirm(p=>!p)} className="absolute right-3 top-9 text-gray-500 hover:text-gray-700">{showConfirm ? '🙈' : '👁️'}</button>
                <InputError message={errors.password_confirmation} className="mt-2" />
              </div>
            </div>

            <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <InputLabel htmlFor="refer_by" value="Referral ID (Sponsor code)" />
                  <TextInput id="refer_by" name="refer_by" value={data.refer_by} className="mt-1 block w-full" placeholder="e.g., ROOT1234" onChange={e=>setData('refer_by',e.target.value.trim())} required />
                  <InputError message={errors.refer_by} className="mt-2" />
                </div>

                <div>
                  <InputLabel value="Placement side" />
                  <div className="mt-2 grid grid-cols-2 overflow-hidden rounded-lg border border-slate-200">
                    <button type="button" onClick={()=>setData('side','L')} className={`py-2 text-sm font-medium ${data.side==='L'?'bg-emerald-600 text-white':'bg-white text-slate-700 hover:bg-slate-50'}`}>Left</button>
                    <button type="button" onClick={()=>setData('side','R')} className={`py-2 text-sm font-medium ${data.side==='R'?'bg-emerald-600 text-white':'bg-white text-slate-700 hover:bg-slate-50'}`}>Right</button>
                  </div>
                  <InputError message={errors.side} className="mt-2" />
                </div>
              </div>

              <label className="mt-3 flex cursor-pointer items-center gap-2">
                <input id="spillover" type="checkbox" checked={!!data.spillover} onChange={e=>setData('spillover',e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600" />
                <span className="text-sm text-gray-700">If chosen side is occupied, auto place in free slot (spillover)</span>
              </label>
              <InputError message={errors.spillover} className="mt-2" />
            </div>

            <div className="flex items-center justify-between pt-2">
              <Link href={route('login')} className="text-sm font-medium text-slate-600 underline hover:text-slate-900">Already registered?</Link>
              <PrimaryButton className="ms-4" disabled={processing}>{processing ? 'Registering…' : 'Register'}</PrimaryButton>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
