<template>
  <div class="flex flex-col gap-4">
    <div class="rounded-lg border border-surface-200 dark:border-surface-700 p-3">
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <h4 class="font-semibold">Membres</h4>
        <span class="text-xs text-surface-600 dark:text-surface-300">
          Repris automatiquement de la liste des membres.
        </span>
      </div>

      <p v-if="lines.length === 0" class="text-sm text-surface-600 dark:text-surface-300 py-2">
        Aucun membre actif à afficher.
      </p>
      <ul v-else class="flex flex-col gap-1">
        <li v-for="line in lines" :key="line" class="font-mono text-sm">{{ line }}</li>
      </ul>

      <p class="text-xs text-surface-600 dark:text-surface-300 mt-3">
        Pour changer un nom ou un instrument, passez par
        <RouterLink :to="membersLink" class="text-primary hover:underline">
          les paramètres du Band Space
        </RouterLink>
        . Le document reste ainsi à jour quand quelqu'un rejoint le groupe ou le quitte.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <!-- A real label rather than an aria-label: the text is on screen anyway, and an
           aria-label would override it and leave the two able to disagree. The id is generated
           because a rider may hold more than one contacts item. -->
      <ToggleSwitch
        :model-value="showEmails"
        :disabled="readOnly"
        :input-id="`${uid}-show-emails`"
        @update:model-value="handleToggleEmails"
      />
      <label :for="`${uid}-show-emails`" class="text-sm">
        Afficher les adresses e-mail des membres
      </label>
    </div>

    <!-- Stated rather than assumed. A rider goes to people outside the band, so publishing four
         addresses should be a decision somebody took on purpose. -->
    <Message v-if="showEmails" severity="warn" :closable="false" size="small">
      Les adresses seront visibles par toute personne recevant ce document.
    </Message>

    <ul v-if="emails.length > 0" class="flex flex-col gap-1">
      <li v-for="email in emails" :key="email" class="font-mono text-sm">{{ email }}</li>
    </ul>

    <div>
      <h4 class="font-semibold mb-1">Notes</h4>
      <p class="text-xs text-surface-600 dark:text-surface-300 mb-2">
        Tout ce que la liste des membres ne couvre pas : régisseur, numéros de téléphone, contact
        de tournée.
      </p>
      <RiderTextItemEditor
        :item-id="itemId"
        :title="title"
        :content="note"
        :read-only="readOnly"
        @save="handleSaveNote"
      />
    </div>
  </div>
</template>

<script setup>
import Message from 'primevue/message'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, useId } from 'vue'
import { RouterLink } from 'vue-router'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import RiderTextItemEditor from './RiderTextItemEditor.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  itemId: { type: String, required: true },
  title: { type: String, required: true },
  /** `{show_emails, lines, emails}`, rendered server side from the current roster. */
  contacts: { type: Object, default: null },
  /** The item's stored `content`: the emails choice and the free text note. */
  content: { type: Object, default: null },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['save'])

const uid = useId()

const lines = computed(() => props.contacts?.lines ?? [])
const emails = computed(() => props.contacts?.emails ?? [])

// Read from the rendered block rather than from content, so the switch shows what the server
// actually applied instead of what the client believes it asked for.
const showEmails = computed(() => props.contacts?.show_emails === true)

const note = computed(() => props.content?.note ?? null)

const membersLink = computed(() => ({
  name: BAND_SPACE_ROUTES.PARAMETERS,
  params: { id: props.bandSpaceId }
}))

/**
 * Both halves of `content` are always written together. The endpoint replaces the whole column,
 * so sending only the changed half would drop the other one.
 */
function handleToggleEmails(value) {
  emit('save', { itemId: props.itemId, content: { showEmails: value === true, note: note.value } })
}

function handleSaveNote({ content }) {
  emit('save', { itemId: props.itemId, content: { showEmails: showEmails.value, note: content } })
}
</script>
