import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// ❗️ Default ko Laravel/Cellveda mat रहने दो; env से लो, वरना 'Sellvada'
const appName = import.meta.env.VITE_APP_NAME ?? 'Cellveda';

createInertiaApp({
  // ✅ Agar page <Head title="..."> de to "Page - App", warna sirf "App"
  title: (title) => (title ? `${title} - ${appName}` : appName),

  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#4B5563' },
});
