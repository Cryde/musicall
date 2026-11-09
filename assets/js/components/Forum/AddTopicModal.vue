<template>
  <Dialog
    v-model:visible="visibleModel"
    modal
    :header="t('forum.add_topic.title')"
    :style="{ width: '50rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
  >
    <template v-if="!isSent">
      <div class="flex flex-col gap-4">
        <div v-if="!forumSlug" class="flex flex-col gap-2">
          <label id="add-topic-forum-label" class="font-medium">{{ t('forum.common.forum') }}</label>
          <Select
            v-model="pickedForumSlug"
            aria-labelledby="add-topic-forum-label"
            :options="forumGroups"
            option-label="title"
            option-value="slug"
            option-group-label="title"
            option-group-children="forums"
            :placeholder="t('forum.add_topic.forum_placeholder')"
            :loading="forumStore.isLoading"
            :invalid="forumStore.hasCategoriesError"
            fluid
          >
            <!-- A slot, as an empty empty-message falls back on PrimeVue's own untranslated text. -->
            <template #empty>
              <template v-if="!forumStore.isLoading">{{ t('forum.index.empty') }}</template>
            </template>
          </Select>
          <small v-if="forumStore.hasCategoriesError" class="text-red-500">
            {{ t('forum.index.load_error') }}
          </small>
        </div>

        <div class="flex flex-col gap-2">
          <label for="title" class="font-medium">{{ t('forum.add_topic.title_label') }}</label>
          <InputText
            id="title"
            v-model="title"
            :placeholder="t('forum.add_topic.title_placeholder')"
            :invalid="title.length > 0 && title.length < MIN_TITLE_LENGTH"
          />
          <small v-if="title.length > 0 && title.length < MIN_TITLE_LENGTH" class="text-red-500">
            {{ t('forum.add_topic.title_too_short', { min: MIN_TITLE_LENGTH }) }}
          </small>
        </div>

        <div class="flex flex-col gap-2">
          <label class="font-medium">{{ t('forum.add_topic.message_label') }}</label>
          <MessageEditor
            ref="editorRef"
            :previous-content="initialMessage"
            :is-restored-draft="initialMessage !== ''"
            @content-update="handleContentUpdate"
            @sign-in-required="emitSignInRequired('image')"
          />
          <small v-if="contentText.length > 0 && contentText.length < MIN_MESSAGE_LENGTH" class="text-red-500">
            {{ t('forum.add_topic.message_too_short', { min: MIN_MESSAGE_LENGTH }) }}
          </small>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="text-center py-8">
        <i class="pi pi-check-circle text-6xl text-green-500 mb-4" />
        <p class="text-lg">{{ t('forum.add_topic.created') }}</p>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          v-if="!isSent"
          :label="t('forum.common.cancel')"
          severity="secondary"
          @click="visibleModel = false"
        />
        <Button
          v-if="isSent"
          :label="t('forum.add_topic.close')"
          severity="secondary"
          @click="visibleModel = false"
        />
        <Button
          v-if="!isSent"
          :label="t('forum.add_topic.create')"
          icon="pi pi-send"
          :disabled="!canSubmit"
          :loading="isSending"
          @click="handleSubmit"
        />
        <Button
          v-if="isSent && createdTopic"
          :label="t('forum.add_topic.go_to_topic')"
          icon="pi pi-eye"
          @click="handleGoToTopic"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForumStore } from '../../store/forum/forum.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { forumPickerGroups } from '../../utils/forumPicker.js'
import MessageEditor from './MessageEditor.vue'

const MIN_TITLE_LENGTH = 5
const MIN_MESSAGE_LENGTH = 10

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  /** Without one, the member picks the forum in the dialog (#1080). */
  forumSlug: {
    type: String,
    default: null
  },
  /** Reopened on what a visitor wrote before signing in (#1305). */
  initialTitle: {
    type: String,
    default: ''
  },
  initialMessage: {
    type: String,
    default: ''
  }
})

// `sign-in-required` carries what a visitor wrote when they click « Créer » or pick an image: nothing is sent for them.
const emit = defineEmits(['update:visible', 'created', 'sign-in-required'])

const { t } = useI18n()
const forumStore = useForumStore()
const userSecurityStore = useUserSecurityStore()
const toast = useToast()

const title = ref('')
const contentHtml = ref('')
const contentText = ref('')
const isSending = ref(false)
const isSent = ref(false)
const createdTopic = ref(null)
const editorRef = ref(null)
const pickedForumSlug = ref(null)

const visibleModel = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const targetForumSlug = computed(() => props.forumSlug ?? pickedForumSlug.value)
const forumGroups = computed(() => forumPickerGroups(forumStore.categories))

// Not while the session is checked: a visitor's click would reach the API, and its 401 would lose the text.
const canSubmit = computed(() => {
  return (
    !userSecurityStore.isAuthenticatedLoading &&
    targetForumSlug.value !== null &&
    title.value.trim().length >= MIN_TITLE_LENGTH &&
    contentText.value.trim().length >= MIN_MESSAGE_LENGTH
  )
})

function handleContentUpdate({ html, text }) {
  contentHtml.value = html
  contentText.value = text
}

/** @param {'publish' | 'image'} reason  what the visitor was doing when stopped */
function emitSignInRequired(reason) {
  emit('sign-in-required', { title: title.value, html: contentHtml.value, reason })
}

async function handleSubmit() {
  if (!canSubmit.value) return
  if (!userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated) {
    emitSignInRequired('publish')
    return
  }

  isSending.value = true
  try {
    const topic = await forumStore.createTopic({
      forumSlug: targetForumSlug.value,
      title: title.value,
      message: contentHtml.value
    })
    trackUmamiEvent('forum-topic-create', { forum: targetForumSlug.value })
    createdTopic.value = topic
    isSent.value = true
    emit('created', topic)
  } catch (error) {
    console.error('Failed to create topic:', error)
    toast.add({
      severity: 'error',
      summary: t('forum.common.error'),
      detail:
        error.response?.status === 429
          ? t('forum.add_topic.rate_limited')
          : t('forum.add_topic.create_failed'),
      life: 5000
    })
  } finally {
    isSending.value = false
  }
}

function handleGoToTopic() {
  visibleModel.value = false
}

// Immediate, since a parent may mount the dialog already open.
watch(
  () => visibleModel.value && !props.forumSlug,
  (needsForumPicker) => {
    if (needsForumPicker) forumStore.loadCategories()
  },
  { immediate: true }
)

// Filled in when the dialog opens, reset when it closes. Immediate, since a parent may mount it already open.
watch(
  visibleModel,
  (newValue) => {
    if (newValue) {
      title.value = props.initialTitle
      return
    }
    pickedForumSlug.value = null
    title.value = ''
    contentHtml.value = ''
    contentText.value = ''
    isSent.value = false
    createdTopic.value = null
    editorRef.value?.reset()
  },
  { immediate: true }
)
</script>
