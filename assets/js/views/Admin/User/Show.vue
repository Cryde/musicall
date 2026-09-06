<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center gap-3">
      <Button
        icon="pi pi-arrow-left"
        severity="secondary"
        text
        rounded
        aria-label="Retour à la gestion des utilisateurs"
        @click="router.push({ name: 'admin_users_dashboard' })"
      />
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-100">
        {{ user?.username ?? 'Utilisateur' }}
      </h1>
      <Tag v-if="user?.is_deleted" value="Compte fermé" severity="danger" />
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <ProgressSpinner style="width: 50px; height: 50px" />
    </div>

    <Message v-else-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>

    <Tabs v-else v-model:value="activeTab">
      <TabList>
        <Tab value="summary">Résumé</Tab>
        <Tab value="roles">Rôles</Tab>
        <Tab value="moderation">
          Modération
          <ComingSoonBadge class="ml-2" />
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="summary">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Panel header="Identité">
              <dl class="flex flex-col gap-2 text-sm">
                <div v-for="row in identityRows" :key="row.label" class="flex justify-between gap-4">
                  <dt class="text-surface-500 dark:text-surface-400">{{ row.label }}</dt>
                  <dd class="text-right">{{ row.value }}</dd>
                </div>
              </dl>
            </Panel>

            <Panel header="Dates">
              <dl class="flex flex-col gap-2 text-sm">
                <div v-for="row in dateRows" :key="row.label" class="flex justify-between gap-4">
                  <dt class="text-surface-500 dark:text-surface-400">{{ row.label }}</dt>
                  <dd class="text-right">{{ row.value }}</dd>
                </div>
              </dl>
            </Panel>

            <Panel header="Contenus publiés" class="md:col-span-2">
              <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div v-for="count in activityCounts" :key="count.label" class="text-center">
                  <p class="text-2xl font-semibold">{{ count.value }}</p>
                  <p class="text-sm text-surface-500 dark:text-surface-400">{{ count.label }}</p>
                </div>
              </div>
            </Panel>
          </div>
        </TabPanel>

        <TabPanel value="roles">
          <Panel header="Rôles attribués">
            <Message v-if="isSelf" severity="warn" :closable="false" class="mb-4">
              Vous ne pouvez pas modifier vos propres rôles. Demandez à un autre administrateur.
            </Message>
            <Message v-if="roleError" severity="error" :closable="false" class="mb-4">{{ roleError }}</Message>

            <div class="flex flex-col gap-5">
              <div
                v-for="role in GRANTABLE_ROLES"
                :key="role.value"
                class="flex items-start justify-between gap-4"
              >
                <div>
                  <label :for="`role-${role.value}`" class="font-medium">{{ role.label }}</label>
                  <p class="text-sm text-surface-500 dark:text-surface-400">{{ role.description }}</p>
                </div>
                <ToggleSwitch
                  :inputId="`role-${role.value}`"
                  :modelValue="roleSwitches[role.value]"
                  :disabled="isSelf || isSavingRoles"
                  @update:modelValue="(granted) => handleRoleToggle(role, granted)"
                />
              </div>
            </div>
          </Panel>
        </TabPanel>

        <TabPanel value="moderation">
          <Panel header="Modération">
            <div class="text-center py-10 flex flex-col items-center gap-3">
              <i class="pi pi-shield text-4xl text-surface-400" aria-hidden="true" />
              <p class="text-surface-600 dark:text-surface-400 max-w-lg">
                Suspendre un compte ou restreindre ses actions arrivera ici. Rien n'est encore
                décidé sur ce que « suspendu » veut dire, ni sur ce qu'il advient des contenus déjà
                publiés, donc rien n'est encore construit.
              </p>
            </div>
          </Panel>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <ConfirmDialog group="admin-user-role" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import ProgressSpinner from 'primevue/progressspinner'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import adminUserApi from '../../../api/admin/user.js'
import ComingSoonBadge from '../../../components/Admin/ComingSoonBadge.vue'
import { GRANTABLE_ROLES } from '../../../constants/adminUser.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { formatDate } from '../../../utils/date.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const userSecurityStore = useUserSecurityStore()

const user = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')
const roleError = ref('')
const isSavingRoles = ref(false)
const activeTab = ref('summary')

/**
 * The switch positions, held here rather than read straight off `user.roles`.
 *
 * PrimeVue's ToggleSwitch keeps its own visual state once clicked, and it only resyncs when the
 * bound value actually changes. Binding it to `user.roles` meant that cancelling the confirmation
 * left the prop on the value it already had, so nothing resynced and the switch sat there claiming
 * the account was an administrator when it was not. Moving the flip here and putting it back makes
 * the revert a real change, which is the only thing the component reacts to.
 */
const roleSwitches = ref({})

function syncSwitchesFromUser() {
  roleSwitches.value = Object.fromEntries(
    GRANTABLE_ROLES.map((role) => [role.value, user.value?.roles?.includes(role.value) ?? false])
  )
}

useTitle(() => `${user.value?.username ?? 'Utilisateur'} - Admin - MusicAll`)

// The API refuses it anyway; the UI disables the switches so the refusal is not a surprise.
const isSelf = computed(() => user.value?.username === userSecurityStore.user?.username)

const identityRows = computed(() => [
  { label: "Nom d'utilisateur", value: user.value.username },
  { label: 'Email', value: user.value.email },
  { label: 'Email confirmé', value: user.value.is_email_confirmed ? 'Oui' : 'Non' },
  { label: 'Profil musicien', value: user.value.has_musician_profile ? 'Oui' : 'Non' },
  { label: 'Profil professeur', value: user.value.has_teacher_profile ? 'Oui' : 'Non' },
  { label: 'Identifiant', value: user.value.id }
])

const dateRows = computed(() => [
  { label: 'Inscription', value: formatOrDash(user.value.creation_datetime) },
  { label: 'Dernière connexion', value: formatOrDash(user.value.last_login_datetime) },
  { label: 'Dernière activité', value: formatOrDash(user.value.last_activity_datetime) },
  { label: 'Email confirmé le', value: formatOrDash(user.value.confirmation_datetime) },
  {
    label: "Nom d'utilisateur changé le",
    value: formatOrDash(user.value.username_changed_datetime)
  },
  { label: 'Compte fermé le', value: formatOrDash(user.value.deletion_datetime) }
])

const activityCounts = computed(() => [
  { label: 'Publications', value: user.value.activity?.publications ?? 0 },
  { label: 'Commentaires', value: user.value.activity?.comments ?? 0 },
  { label: 'Messages forum', value: user.value.activity?.forum_posts ?? 0 },
  { label: 'Annonces', value: user.value.activity?.musician_announces ?? 0 },
  { label: 'Band Spaces', value: user.value.activity?.band_spaces ?? 0 }
])

function formatOrDash(value) {
  return value ? formatDate(value) : '-'
}

function handleRoleToggle(role, granted) {
  // Follow the click straight away, so the switch does not feel stuck while the confirmation is up.
  roleSwitches.value[role.value] = granted

  if (!granted || !role.requiresConfirmation) {
    saveRoles(nextRoles(role.value, granted))
    return
  }

  confirm.require({
    group: 'admin-user-role',
    header: 'Confirmation',
    message: `Donner le rôle « ${role.label} » à ${user.value.username} ? Cela donne un accès complet à l'administration.`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Confirmer',
    acceptClass: 'p-button-danger',
    accept: () => saveRoles(nextRoles(role.value, true)),
    // reject covers the Annuler button, onHide the close icon and Escape. Both are wired because a
    // dismissal that does not put the switch back leaves the screen claiming the account is an
    // administrator when it is not. onHide runs after accept too, which is harmless: by then the
    // save has refreshed the user and the switches follow it.
    reject: () => syncSwitchesFromUser(),
    onHide: () => syncSwitchesFromUser()
  })
}

/**
 * The whole grantable set is sent, so the request states what the roles should be rather than how
 * they changed. Roles the account holds that this screen cannot grant are preserved server side.
 */
function nextRoles(role, granted) {
  const grantable = GRANTABLE_ROLES.map((candidate) => candidate.value)
  const kept = user.value.roles.filter((held) => grantable.includes(held) && held !== role)

  return granted ? [...kept, role] : kept
}

async function saveRoles(roles) {
  isSavingRoles.value = true
  roleError.value = ''
  try {
    await adminUserApi.updateRoles(user.value.id, roles)
  } catch (e) {
    roleError.value = e?.response?.data?.detail || 'La mise à jour des rôles a échoué.'
    syncSwitchesFromUser()
    isSavingRoles.value = false

    return
  }

  // The save landed. A failure from here on is a stale display, not a failed change, and saying
  // otherwise would send an admin back to re-grant a role they already granted.
  try {
    user.value = await adminUserApi.get(user.value.id)
    syncSwitchesFromUser()
    toast.add({ severity: 'success', summary: 'Rôles mis à jour', life: 3000 })
  } catch {
    roleError.value =
      "Les rôles ont bien été enregistrés, mais l'affichage n'a pas pu être actualisé. Rechargez la page."
  } finally {
    isSavingRoles.value = false
  }
}

onMounted(async () => {
  try {
    user.value = await adminUserApi.get(route.params.id)
    syncSwitchesFromUser()
  } catch (e) {
    errorMessage.value =
      e?.response?.status === 404
        ? 'Utilisateur introuvable.'
        : e?.response?.data?.detail || 'Impossible de charger cet utilisateur.'
  } finally {
    isLoading.value = false
  }
})
</script>
