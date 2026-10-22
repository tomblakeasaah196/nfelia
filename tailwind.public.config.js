// Tailwind for the pages outside the signed-in app that used to load the
// Tailwind CDN compiler at run time (sign-in links, password reset, the
// public documents customers open, delivery sign-off, deeds, onboarding).
// Compiled ahead of time by `npm run build:public` into
// assets/css/public.css, which is committed: the server has no Node.
//
// Fonts and the business's own colour are CSS variables, set inline by
// each page (a document shows the business's fonts; everything else shows
// Nfelia's), so one stylesheet serves every business. Words in the brand
// colour take brand-text, its shade that reads on white; words on a fill of
// it take on-brand, white or ink (brand_doc_colours(), app/helpers/brand.php).
module.exports = {
  content: [
    './views/auth/login-link.php', './views/auth/recovery-sent.php', './views/auth/signin.php',
    './views/auth/forgot-password.php', './views/auth/reset-password.php',
    './views/quotations/public.php', './views/invoices/public.php', './views/receipts/public.php',
    './views/deliveries/public.php', './views/deliveries/signoff.php', './views/deeds/view.php',
    './views/onboarding/wizard.php',
    './views/partials/password_rules.php', './views/partials/pwa_head.php', './views/partials/doc_*.php',
    './assets/js/password.js',
    // A delivery's status badge, whose colours DeliveryController::STATUSES names
    './app/controllers/DeliveryController.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT:   'rgb(var(--brand-rgb) / <alpha-value>)',
          primary:   'rgb(var(--brand-rgb) / <alpha-value>)',
          secondary: 'rgb(var(--brand-secondary-rgb) / <alpha-value>)',
          text:      'rgb(var(--brand-text-rgb) / <alpha-value>)',
          plum: '#1B0A11',
          sand: '#E8E4DA',
          cream: '#F2EFE7',
          white: '#FFFFFF',
          gold: '#B69F5E',
          burgundy: '#8B004A',
        },
        'on-brand': 'var(--on-brand)',
      },
      fontFamily: {
        sans:       ['var(--font-body)'],
        body:       ['var(--font-body)'],
        heading:    ['var(--font-heading)'],
        serif:      ['var(--font-serif)'],
        mono:       ['var(--font-mono)'],
        jakarta:    ['"Plus Jakarta Sans"', 'sans-serif'],
        montserrat: ['var(--font-body)'],
      },
      boxShadow: {
        'flat':      '0 4px 15px rgba(27, 10, 17, 0.03)',
        'flat-hard': '4px 4px 0px 0px rgba(27, 10, 17, 1)',
      },
    },
  },
};
