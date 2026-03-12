export default defineNuxtConfig({
  modules: ['@nuxt/ui'],

  compatibilityDate: '2026-03-12',

  ssr: false,

  devtools: { enabled: false },

  nitro: {
    preset: 'netlify-static',
  },

  runtimeConfig: {
    public: {
      apiBase: '',
    },
  },

  app: {
    head: {
      title: 'CV Scoring System · Talently',
      htmlAttrs: { lang: 'es' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Evaluación automática de perfiles con IA' },
      ],
    },
  },
})
