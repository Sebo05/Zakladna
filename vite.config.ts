import { defineConfig } from 'vite';
import nette from '@nette/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
	plugins: [
		nette({
			entry: ['main.js', 'main.css'],
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
