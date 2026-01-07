<template>
    <div class="relative min-h-200 bg-surface-50 dark:bg-surface-950">
        <Toast />
        <Menu />
        <div class="bg-surface-200 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col gap-8">
                <router-view/>
            </div>
        </div>
        <Footer/>
        <CookieConsent />
    </div>
</template>
<script setup>
import Toast from 'primevue/toast'
import CookieConsent from './CookieConsent.vue'
import Footer from '../views/Global/Footer.vue'
import Menu from '../views/Global/Menu.vue'
</script>
