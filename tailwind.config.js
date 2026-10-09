import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// MyBooks colours (rebrand R1). The same values live in config/brand.php
// for PDFs, emails, charts and the app manifest; change both together.
const brand = {
    50: '#EEF3F8',
    100: '#D9E4EF',
    200: '#B4C8DD',
    300: '#8AA9CB',
    400: '#5F84B0',
    500: '#3A6798',
    600: '#1F4E79', // the brand colour: main buttons, links
    700: '#183E61',
    800: '#132F4A',
    900: '#102A43', // sidebar
    950: '#0A1B2D',
};

// Ochre accent: small highlights only. Never white text on it.
const accent = {
    50: '#FDF8EC',
    100: '#FBEFD5',
    200: '#F5D9A3',
    300: '#E9BD67',
    400: '#D79E36',
    500: '#C0841A',
    600: '#A86B12',
    700: '#8A5A12',
    800: '#6E470F',
    900: '#4F330B',
};

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            colors: { brand, accent },
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
