// Tailwind for the signed-in app, compiled ahead of time by `npm run build:css`
// into assets/css/app.css (committed: the server has no Node).
// Colours that change per business are CSS variables set by the layout,
// so one stylesheet serves every business.
module.exports = {
  content: ['./index.php', './views/**/*.php', './app/**/*.php', './api/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        // The business's colour, worked out by app/helpers/theme.php:
        // bg-accent with text-on-accent for fills, text-accent-text for
        // links (readable on white), bg-accent-soft for selected tints.
        accent: {
          DEFAULT: 'rgb(var(--accent-rgb) / <alpha-value>)',
          text: 'var(--accent-text)',
          soft: 'var(--accent-soft)',
        },
        'on-accent': 'var(--on-accent)',
        brand: {
          primary: 'rgb(var(--brand-primary-rgb) / <alpha-value>)',
          secondary: 'rgb(var(--brand-secondary-rgb) / <alpha-value>)',
          plum: '#1B0A11',
          sand: '#E8E4DA',
          cream: '#F2EFE7',
          white: '#FFFFFF',
          gold: '#B69F5E',
          burgundy: '#8B004A',
        },
      },
      fontFamily: {
        heading: ['var(--font-heading)'],
        body: ['var(--font-body)'],
        mono: ['var(--font-body)'],
      },
    },
  },
};
