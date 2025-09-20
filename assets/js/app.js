import { createApp } from 'vue';
import '../style/style.css';
import PrimeVue from 'primevue/config';
import App from './App.vue';
import Aura from '@primeuix/themes/aura';
import router from './router/index.js';
import { createPinia } from 'pinia'
import Ripple from 'primevue/ripple';
import { configure } from "vue-gtag";

const pinia = createPinia()
const app = createApp(App)
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
