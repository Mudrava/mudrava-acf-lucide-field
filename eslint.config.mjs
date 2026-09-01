import globals from 'globals';

export default [
	{
		ignores: ['vendor/**', 'node_modules/**', 'data/**', 'assets/*.svg'],
	},
	{
		files: ['assets/js/**/*.js'],
		languageOptions: {
			ecmaVersion: 2018,
			sourceType: 'script',
			globals: {
				...globals.browser,
				acf: 'readonly',
				jQuery: 'readonly',
				mudravaLucideField: 'readonly',
			},
		},
		rules: {
			'no-undef': 'error',
			'no-unused-vars': ['error', { args: 'none' }],
			'no-alert': 'error',
			'no-eval': 'error',
			'no-implied-eval': 'error',
			'no-new-func': 'error',
			curly: ['error', 'all'],
			eqeqeq: 'error',
		},
	},
	{
		files: ['scripts/**/*.mjs', 'tests/e2e/**/*.{js,mjs}', 'playwright.config.mjs'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.node, ...globals.browser },
		},
	},
];
