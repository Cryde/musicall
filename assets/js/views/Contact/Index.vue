<template>
  <div class="py-6 md:py-10">
    <div class="max-w-2xl mx-auto">
      <h1 class="text-3xl font-semibold text-surface-900 dark:text-surface-0 mb-6">Contact</h1>

      <div v-if="isSent" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
        <i class="pi pi-check-circle text-6xl text-green-500 mb-4" />
        <p class="text-xl text-surface-700 dark:text-surface-300">
          Votre message a bien été envoyé !
        </p>
        <Button
          label="Retour à l'accueil"
          icon="pi pi-home"
          class="mt-6"
          @click="router.push({ name: 'app_home' })"
        />
      </div>

      <div v-else class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6 md:p-8">
        <p class="text-surface-600 dark:text-surface-400 mb-6">
          Une question ? Une suggestion ?<br />
          Remplissez le formulaire ci-dessous.
        </p>

        <Message v-if="errors.length" severity="error" :closable="false" class="mb-6">
          <ul class="list-disc list-inside">
            <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
          </ul>
        </Message>

        <div class="flex flex-col gap-5">
          <div class="flex flex-col gap-2">
            <label for="name" class="font-medium text-surface-700 dark:text-surface-300">
              Votre nom
            </label>
            <InputText
              id="name"
              v-model="name"
              placeholder="Entrez votre nom"
              :invalid="submitted && !name.trim()"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="email" class="font-medium text-surface-700 dark:text-surface-300">
              Votre email
            </label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              placeholder="Entrez votre email"
              :invalid="submitted && !email.trim()"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="message" class="font-medium text-surface-700 dark:text-surface-300">
              Votre message
            </label>
            <Textarea
              id="message"
              v-model="message"
              rows="6"
              placeholder="Entrez votre message"
              :invalid="submitted && !message.trim()"
            />
            <small class="text-surface-500">Minimum 10 caractères</small>
          </div>

          <Button
            label="Envoyer"
            icon="pi pi-send"
            :loading="isSending"
            :disabled="!canSend || isSending"
            @click="send"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import contactApi from '../../api/contact/contact.js'

useTitle('Contact - MusicAll')

const router = useRouter()

const name = ref('')
const email = ref('')
const message = ref('')
const isSending = ref(false)
const isSent = ref(false)
const errors = ref([])
const submitted = ref(false)

const canSend = computed(() => {
  return name.value.trim().length > 0 && email.value.trim().length > 0 && message.value.trim().length >= 10
})

async function send() {
  submitted.value = true
  if (!canSend.value) return

  isSending.value = true
  errors.value = []

  try {
    await contactApi.send({
      name: name.value,
      message: message.value,
      email: email.value
    })
    isSent.value = true
  } catch (e) {
    if (e.response?.data?.violations) {
      errors.value = e.response.data.violations.map((item) => item.message)
    } else {
      errors.value = ['Une erreur est survenue. Veuillez réessayer.']
    }
  } finally {
    isSending.value = false
  }
}
</script>
