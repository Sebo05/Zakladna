import { defineConfig } from 'vite';
import nette from '@nette/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
	root: 'www/Frontend',
	plugins: [
		nette({
			entry: ['js/main.js', 'css/main.css'],
		}),
		tailwindcss(),
	],

	build: {
		emptyOutDir: true,
		chunkSizeWarningLimit: 1500,
	},

	css: {
		devSourcemap: true,
	},
});
