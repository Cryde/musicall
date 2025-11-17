<template>
    <div class="py-10 md:py-20 flex items-center justify-center">
        <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
            <div class="flex flex-col items-center gap-6 w-full">
                <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
                    Welcome Back
                </h1>
            </div>
            <div class="flex items-center gap-4 w-full">
                <Button outlined icon="pi pi-google text-base! leading-none!" severity="secondary" class="flex-1! py-2! text-surface-900! dark:text-surface-0!" />
                <Button outlined icon="pi pi-facebook text-base! leading-none!" severity="secondary" class="flex-1! py-2! text-surface-900! dark:text-surface-0!" />
            </div>
            <div class="flex items-center gap-2 w-full">
                <Divider>ou</Divider>
            </div>
            <div class="flex flex-col gap-6 w-full">

                <Message severity="error" v-if="userSecurity.loginErrors.length > 0">
                    <span v-for="error in userSecurity.loginErrors">{{ error }}</span>
                </Message>

                <div class="flex flex-col gap-2">
                    <label for="email" class="text-surface-900 dark:text-surface-0 font-medium">Email ou nom d'utilisateur</label>
                    <InputText id="email" v-model="email" placeholder="Email ou nom d'utilisateur" class="p-3 shadow-sm dark:bg-surface-900!" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">Mot de passe</label>
                    <Password id="password" v-model="password" placeholder="Mot de passe" :toggleMask="true" :feedback="false" input-class="w-full! dark:bg-surface-900!" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between w-full gap-3 sm:gap-0">
                <a class="text-surface-500 dark:text-surface-400 font-medium cursor-pointer hover:text-surface-600 dark:hover:text-surface-300">Mot de passe oublié ?</a>
            </div>
            <div class="flex flex-col gap-10 w-full">
                <Button
                    label="Me connecter"
                    class="w-full"
                    :loading="isLoginSubmitting"
                    :disabled="isLoginSubmitting"
                    @click="sendLogin"
                />
                <div class="text-center w-full">
                    <span class="text-surface-900 dark:text-surface-0 font-medium">Vous n'avez pas de compte ?</span>
                    <a class="ml-3 text-primary font-medium cursor-pointer hover:text-primary-emphasis">Créer un compte en cliquant ici !</a>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import { ref } from 'vue'
import { useUserSecurityStore } from '../../store/user/security.js'

const userSecurity = useUserSecurityStore()

const email = ref('')
const password = ref('')
const isLoginSubmitting = ref(false)

async function sendLogin() {
  isLoginSubmitting.value = true
  await userSecurity.login(email.value, password.value)
  isLoginSubmitting.value = false
}
</script>
