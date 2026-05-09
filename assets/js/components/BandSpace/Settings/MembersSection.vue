<template>
  <div class="flex flex-col gap-6">
    <!-- Invite form -->
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
      <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4">
        Inviter un membre
      </h3>
      <form @submit.prevent="handleInvite" class="flex gap-3">
        <InputText
          v-model="inviteIdentifier"
          placeholder="Email ou nom d'utilisateur"
          class="flex-1"
          :disabled="settingsStore.isInviting"
        />
        <Button
          type="submit"
          label="Inviter"
          icon="pi pi-send"
          :loading="settingsStore.isInviting"
          :disabled="!inviteIdentifier.trim()"
        />
      </form>
      <small v-if="inviteError" class="text-red-500 mt-2 block">{{ inviteError }}</small>
      <small v-if="inviteSuccess" class="text-green-600 mt-2 block">{{ inviteSuccess }}</small>
    </div>

    <!-- Pending invitations -->
    <div v-if="settingsStore.invitations.length > 0" class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
      <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4">
        Invitations en attente
      </h3>
      <div class="flex flex-col gap-3">
        <div
          v-for="invitation in settingsStore.invitations"
          :key="invitation.id"
          class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
        >
          <div>
            <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
              {{ invitation.email }}
            </span>
            <span class="text-xs text-surface-500 dark:text-surface-400 ml-2">
              Expire le {{ formatDate(invitation.expiration_datetime) }}
            </span>
          </div>
          <Button
            icon="pi pi-times"
            text
            rounded
            severity="danger"
            size="small"
            v-tooltip.top="'Annuler l\'invitation'"
            :loading="settingsStore.isCancellingInvitation"
            @click="handleCancelInvitation(invitation)"
          />
        </div>
      </div>
    </div>

    <!-- Members list -->
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
      <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4">Membres</h3>

      <ProgressSpinner v-if="settingsStore.isLoadingMembers" class="flex justify-center" />

      <div v-else class="flex flex-col gap-3">
        <div
          v-for="member in settingsStore.members"
          :key="member.id"
          class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
        >
          <div class="flex items-center gap-3">
            <img
              v-if="member.profile_picture_url"
              :src="member.profile_picture_url"
              :alt="member.username"
              class="w-8 h-8 rounded-full object-cover"
            />
            <div
              v-else
              class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-primary-contrast text-sm font-semibold"
            >
              {{ member.username.charAt(0).toUpperCase() }}
            </div>
            <div>
              <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
                {{ member.username }}
              </span>
              <span v-if="isMe(member)" class="text-xs text-surface-400 ml-1">(vous)</span>
            </div>
            <Tag
              :value="member.role === 'admin' ? 'Admin' : 'Membre'"
              :severity="member.role === 'admin' ? 'warn' : 'info'"
              class="text-xs"
            />
          </div>

          <div v-if="!isMe(member)" class="flex gap-1">
            <Button
              v-if="member.role === 'user'"
              icon="pi pi-arrow-up"
              text
              rounded
              size="small"
              v-tooltip.top="'Promouvoir admin'"
              :loading="settingsStore.isUpdatingRole"
              @click="handlePromote(member)"
            />
            <Button
              v-if="member.role === 'admin'"
              icon="pi pi-arrow-down"
              text
              rounded
              size="small"
              v-tooltip.top="'Rétrograder membre'"
              :loading="settingsStore.isUpdatingRole"
              @click="handleDemote(member)"
            />
            <Button
              icon="pi pi-user-minus"
              text
              rounded
              severity="danger"
              size="small"
              v-tooltip.top="'Exclure'"
              :loading="settingsStore.isKicking"
              @click="handleKick(member)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Leave -->
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100">
            Quitter le Band Space
          </h3>
          <p v-if="isOnlyAdmin" class="text-xs text-surface-500 mt-1">
            Vous devez promouvoir un autre membre administrateur avant de quitter.
          </p>
        </div>
        <Button
          label="Quitter"
          severity="danger"
          outlined
          :disabled="isOnlyAdmin"
          :loading="settingsStore.isLeaving"
          @click="handleLeave"
        />
      </div>
    </div>

  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { useUserSecurityStore } from '../../../store/user/security.js'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const settingsStore = useBandSpaceSettingsStore()
const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()

const bandSpaceId = route.params.id
const inviteIdentifier = ref('')
const inviteError = ref('')
const inviteSuccess = ref('')

const isMe = (member) => member.username === userSecurityStore.user?.username

const adminCount = computed(() => settingsStore.members.filter((m) => m.role === 'admin').length)
const isOnlyAdmin = computed(() => {
  const me = settingsStore.members.find((m) => isMe(m))
  return me?.role === 'admin' && adminCount.value === 1
})

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

async function handleInvite() {
  inviteError.value = ''
  inviteSuccess.value = ''
  try {
    await settingsStore.invite(bandSpaceId, inviteIdentifier.value.trim())
    inviteSuccess.value = `Invitation envoyée à ${inviteIdentifier.value}`
    inviteIdentifier.value = ''
  } catch (e) {
    inviteError.value = e.message
  }
}

function handleCancelInvitation(invitation) {
  confirm.require({
    message: `Annuler l'invitation envoyée à ${invitation.email} ?`,
    header: "Confirmer l'annulation",
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Non',
    acceptLabel: 'Oui, annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await settingsStore.cancelInvitation(bandSpaceId, invitation.id)
        toast.add({ severity: 'success', summary: 'Invitation annulée', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}

async function handlePromote(member) {
  try {
    await settingsStore.updateRole(bandSpaceId, member.id, 'admin')
    toast.add({
      severity: 'success',
      summary: `${member.username} est maintenant admin`,
      life: 3000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleDemote(member) {
  try {
    await settingsStore.updateRole(bandSpaceId, member.id, 'user')
    toast.add({
      severity: 'success',
      summary: `${member.username} est maintenant membre`,
      life: 3000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function handleKick(member) {
  confirm.require({
    message: `Êtes-vous sûr de vouloir exclure ${member.username} ?`,
    header: "Confirmer l'exclusion",
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Exclure',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await settingsStore.kickMember(bandSpaceId, member.id)
        toast.add({ severity: 'success', summary: `${member.username} a été exclu`, life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}

function handleLeave() {
  confirm.require({
    message: 'Êtes-vous sûr de vouloir quitter ce Band Space ?',
    header: 'Confirmer le départ',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Quitter',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await settingsStore.leave(bandSpaceId)
        await bandSpaceStore.loadMyBandSpaces()
        router.replace({ name: BAND_SPACE_ROUTES.INDEX })
        toast.add({ severity: 'info', summary: 'Vous avez quitté le Band Space', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}

onMounted(() => {
  settingsStore.loadMembers(bandSpaceId)
  settingsStore.loadInvitations(bandSpaceId)
})

onUnmounted(() => {
  settingsStore.clear()
})
</script>
