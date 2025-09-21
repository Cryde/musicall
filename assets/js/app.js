import { createApp } from 'vue';
import '../style/style.css';
import PrimeVue from 'primevue/config';
import App from './App.vue';
import Aura from '@primeuix/themes/aura';
import router from './router/index.js';
import { createPinia } from 'pinia'
import Ripple from 'primevue/ripple';
import { configure } from "vue-gtag";
import { createHead } from '@unhead/vue/client';

const pinia = createPinia()
const app = createApp(App)
const head = createHead({
  init: [
    {
      title: 'MusicAll, le site de référence au service de la musique',
      htmlAttrs: {
        lang: 'fr' ,
      },
      meta: [
        {vmid: 'description', name: 'description', 'content': 'Site communautaire au service de la musique. Articles et cours sur la musique, annuaire de musiciens, forums, ...'},
        { property: "og:type", content: 'website' },
        { property: "og:locale", content: 'fr_FR' },
        { property: "og:image", content: window.location.origin + '/build/images/facebook-logo.jpg' },
        { property: "og:site_name", content: 'MusicAll' },
      ]
    },
  ]
})
app.use(head);
app.use(PrimeVue, {
  ripple: true,
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.dark-mode',
    }
  }
});
app.directive('ripple', Ripple);
app.use(pinia);
app.use(router);
app.mount('#app');

configure({
  tagId: "G-1CK1G9W6FX",
  pageTracker: {
    router,
  }
})

import.meta.glob([
  '../images/**',
]);
