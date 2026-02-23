import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
	plugins: [vue()],
	resolve: {
		alias: {
			'@': resolve(__dirname, 'src'),
		},
	},
	css: {
		// Process CSS imports without errors (but don't inject into DOM)
	},
	test: {
		globals: true,
		environment: 'happy-dom',
		setupFiles: ['tests/frontend/setup.ts'],
		include: ['tests/frontend/**/*.test.ts'],
		css: true,
		server: {
			deps: {
				inline: [/@nextcloud\/vue/],
			},
		},
	},
})
