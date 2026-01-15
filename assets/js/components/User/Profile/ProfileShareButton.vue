<template>
  <div class="relative">
    <Button
      ref="buttonRef"
      icon="pi pi-share-alt"
      severity="secondary"
      rounded
      v-tooltip.bottom="'Partager'"
      aria-label="Partager le profil"
      @click="handleShare"
    />
    <Menu ref="menuRef" :model="menuItems" :popup="true" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'

const props = defineProps({
  url: { type: String, required: true },
  title: { type: String, required: true }
})

const toast = useToast()
const menuRef = ref()
const buttonRef = ref()

const supportsNativeShare = computed(() => {
  return typeof navigator !== 'undefined' && navigator.share
})

const encodedUrl = computed(() => encodeURIComponent(props.url))
const encodedTitle = computed(() => encodeURIComponent(props.title))

const menuItems = computed(() => [
  {
    label: 'Copier le lien',
    icon: 'pi pi-link',
    command: copyLink
  },
  { separator: true },
  {
    label: 'Twitter / X',
    icon: 'pi pi-twitter',
    command: () => shareToTwitter()
  },
  {
    label: 'Facebook',
    icon: 'pi pi-facebook',
    command: () => shareToFacebook()
  },
  {
    label: 'LinkedIn',
    icon: 'pi pi-linkedin',
    command: () => shareToLinkedIn()
  },
  {
    label: 'WhatsApp',
    icon: 'pi pi-whatsapp',
    command: () => shareToWhatsApp()
  }
])

async function handleShare(event) {
  if (supportsNativeShare.value) {
    try {
      await navigator.share({
        title: props.title,
        url: props.url
      })
    } catch (error) {
      if (error.name !== 'AbortError') {
        menuRef.value.toggle(event)
      }
    }
  } else {
    menuRef.value.toggle(event)
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.url)
    toast.add({
      severity: 'success',
      summary: 'Lien copié',
      detail: 'Le lien a été copié dans le presse-papiers',
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de copier le lien',
      life: 3000
    })
  }
}

function shareToTwitter() {
  const url = `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${encodedTitle.value}`
  openShareWindow(url)
}

function shareToFacebook() {
  const url = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`
  openShareWindow(url)
}

function shareToLinkedIn() {
  const url = `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`
  openShareWindow(url)
}

function shareToWhatsApp() {
  const url = `https://wa.me/?text=${encodedTitle.value}%20${encodedUrl.value}`
  openShareWindow(url)
}

function openShareWindow(url) {
  window.open(url, '_blank', 'width=600,height=400,noopener,noreferrer')
}
</script>
