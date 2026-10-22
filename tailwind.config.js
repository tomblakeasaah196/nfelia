// Tailwind for the signed-in app, compiled ahead of time by `npm run build:css`
// into assets/css/app.css (committed: the server has no Node).
// Colours that change per business are CSS variables set by the layout,
// so one stylesheet serves every business.
//
// The business's colour comes in four parts (app/helpers/theme.php):
//   accent       fills: a main button, the selected tab, today on the calendar
//   on-accent    text and icons on those fills (white or ink, whichever reads)
//   accent-text  text, icons, borders and rings on white (4.5:1 on white)
//   accent-soft  the pale tint behind a selected chip
// Nfelia's own wine and gold are not here: inside the app every colour that
// isn't a neutral or a status (paid, owed) follows the business.
module.exports = {
  content: ['./index.php', './views/**/*.php', './app/**/*.php', './api/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        accent: 'rgb(var(--accent-rgb) / <alpha-value>)',
        'on-accent': 'var(--on-accent)',
        'accent-text': 'rgb(var(--accent-text-rgb) / <alpha-value>)',
        'accent-soft': 'var(--accent-soft)',
        brand: {
          primary: 'rgb(var(--brand-primary-rgb) / <alpha-value>)',
          secondary: 'rgb(var(--brand-secondary-rgb) / <alpha-value>)',
          plum: '#1B0A11',
          sand: '#E8E4DA',
          cream: '#F2EFE7',
          white: '#FFFFFF',
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
