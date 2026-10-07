import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans:    ['Outfit', ...defaultTheme.fontFamily.sans],
                serif:   ['Fraunces', ...defaultTheme.fontFamily.serif],
                heading: ['Fraunces', 'serif'],
                fraunces:['Fraunces', 'serif'],
                syne:    ['Syne', 'sans-serif'],
            },
            colors: {
                primary: {
                    50:  '#FFE3C2',
                    100: '#FFE3C2',
                    200: '#FFE3C2',
                    300: '#FFC857',
                    400: '#F88027',
                    500: '#F88027',
                    600: '#DD6E14',
                    700: '#DD6E14',
                    800: '#4A2E22',
                    900: '#2E1B12',
                    DEFAULT: '#F88027',
                    hover: '#DD6E14',
                    tint: '#FFE3C2',
                },
                ink: '#4A2E22',
                secondary: '#8FBF9F',
                accent: '#FFC857',
                bg: '#FFF9F2',
                surface: '#FFF1E0',
                text: {
                    DEFAULT: '#2E1B12',
                    muted: '#8C7568',
                },
                border: '#F0DCC8',
                espresso: {
                    50:  '#FFF9F2',
                    100: '#FFF1E0',
                    200: '#F0DCC8',
                    300: '#8C7568',
                    400: '#8C7568',
                    500: '#4A2E22',
                    600: '#4A2E22',
                    700: '#2E1B12',
                    800: '#4A2E22',
                    850: '#2E1B12',
                    900: '#2E1B12',
                    950: '#1C0F0A',
                    DEFAULT: '#4A2E22',
                },
                cream: {
                    50:  '#FFF9F2',
                    100: '#FFF1E0',
                    200: '#F0DCC8',
                    300: '#8C7568',
                    400: '#8C7568',
                    DEFAULT: '#FFF9F2',
                },
                rose: {
                    bakery: '#E8B4A0',
                },
            },
            spacing: {
                '18': '4.5rem',
                '22': '5.5rem',
                '88': '22rem',
                '128': '32rem',
            },
            borderRadius: {
                '4xl': '2rem',
            },
            boxShadow: {
                'luxury':    '0 4px 40px rgba(43, 27, 23, 0.08)',
                'luxury-lg': '0 8px 60px rgba(43, 27, 23, 0.12)',
                'card':      '0 2px 20px rgba(200, 151, 106, 0.15)',
                'card-hover':'0 8px 40px rgba(200, 151, 106, 0.25)',
            },
            backgroundImage: {
                'gradient-luxury': 'linear-gradient(135deg, #FEFAF6 0%, #F5EDE3 100%)',
                'gradient-hero':   'linear-gradient(to right, rgba(43,27,23,0.85) 0%, rgba(43,27,23,0.4) 60%, transparent 100%)',
            },
            transitionDuration: {
                '400': '400ms',
            },
            animation: {
                'fade-up':    'fadeUp 0.6s ease-out',
                'fade-in':    'fadeIn 0.5s ease-out',
                'slide-in':   'slideIn 0.4s ease-out',
                'slide-left': 'slideLeft 0.4s ease-out',
                'bounce-soft':'bounceSoft 2s infinite',
            },
            keyframes: {
                fadeUp: {
                    '0%':   { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeIn: {
                    '0%':   { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideIn: {
                    '0%':   { transform: 'translateX(100%)' },
                    '100%': { transform: 'translateX(0)' },
                },
                slideLeft: {
                    '0%':   { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(0)' },
                },
                bounceSoft: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%':      { transform: 'translateY(-8px)' },
                },
            },
            screens: {
                'xs': '480px',
            },
        },
    },

    plugins: [forms, typography],
};
