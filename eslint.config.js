export default [
    {
        files: ['assets/**/*.js'],
        languageOptions: {
            ecmaVersion: 2024,
            sourceType: 'module',
            globals: {document: 'readonly', window: 'readonly', navigator: 'readonly', fetch: 'readonly', URL: 'readonly', setTimeout: 'readonly', clearTimeout: 'readonly'},
        },
        rules: {
            'no-var': 'error',
            'prefer-const': 'error',
            'no-unused-vars': 'error',
            'no-undef': 'error',
            eqeqeq: 'error',
            'max-lines': ['error', 80],
            'max-lines-per-function': ['error', 40],
        },
    },
];
