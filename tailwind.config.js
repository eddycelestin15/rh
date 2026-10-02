/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.php",
    "./pages/**/*.{php,html,js}",
    "./includes/**/*.{php,html,js}",
    "./actions/**/*.{php,html,js}",
    "./api/**/*.{php,html,js}",
    "./assets/js/**/*.js",
    "./documents/**/*.{php,html,js}"
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}