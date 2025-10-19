<template>
  <div class="relative min-h-[50rem] bg-surface-50 dark:bg-surface-950">
    <Menu />
    <div class="bg-surface-200 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
      <div class="flex flex-col gap-8">
        <router-view/>
      </div>
    </div>
    <Footer/>
  </div>
</template>

<script setup>
import Footer from './views/Global/Footer.vue'
import Menu from './views/Global/Menu.vue'
import axios from "axios";
import {useRouter, useRoute} from 'vue-router'
import {useUserSecurityStore} from "./store/user/security.js";
import {onMounted} from "vue";

const userSecurityStore = useUserSecurityStore();
const router = useRouter()
const route = useRoute()

onMounted(async () => {
    await userSecurityStore.checkAuthInfo();

    // check before each route if auth required and check if user is auth
    axios.interceptors.request.use(async function (config) {
        const url = config.url;
        if (!url.includes('login') && !url.includes('refresh') && !url.includes('registration')) {
            await userSecurityStore.checkAuthInfo();

            if (!userSecurityStore.isAuthenticated.value && route.meta.isAuthRequired) {
                await router.replace({name: 'app_home'});
            }
        }

        return config;
    }, function (error) {
        return Promise.reject(error);
    });
})
</script>
