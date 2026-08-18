import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#FFF4F2',
                    100: '#FFE4DF',
                    200: '#FFCCC2',
                    300: '#FFA494',
                    400: '#FF6E56',
                    500: '#FF2D20',
                    600: '#E8210F',
                    700: '#C01A0C',
                    800: '#99150B',
                    900: '#7A130C',
                    950: '#430704',
                },
                /* Semantic variants used across the app (keeps compatibility with existing Bootstrap-like classes) */
                success: '#10B981',
                danger: '#EF4444',
                warning: '#F59E0B',
                info: '#06B6D4',
                secondary: '#6B7280',
            },
        },
    },

    plugins: [forms],
};
