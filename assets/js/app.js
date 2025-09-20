import { createApp } from 'vue';
import '../style/style.css';
import PrimeVue from 'primevue/config';
import App from './App.vue';
import Aura from '@primeuix/themes/aura';
import router from './router/index.js';
import { createPinia } from 'pinia'

const pinia = createPinia()
const app = createApp(App)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.dark-mode',
    }
  }
});
app.use(pinia);
app.use(router);
app.mount('#app');

import.meta.glob([
  '../images/**',
]);
