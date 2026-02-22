import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
    'main-settings': 'src/main-settings.ts',
    'main-files': 'src/main-files.ts',
    'main-overview': 'src/main-overview.ts',
}, {
    inlineCSS: { relativeCSSInjection: true },
})
