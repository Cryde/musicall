<template>
    <div class="bg-surface-0 dark:bg-surface-900 shadow-sm rounded-2xl overflow-hidden h-full flex flex-col">
        <div class="p-4 flex flex-col flex-1">
            <div class="flex gap-3 md:gap-4">
                <!-- Avatar -->
                <router-link v-if="!user.deletion_datetime" :to="profileRoute" class="flex-shrink-0">
                    <Avatar
                        v-if="user.profile_picture_url"
                        :image="user.profile_picture_url"
                        :pt="{ image: { alt: t('search.announce_card.avatar_alt', { name: userName }) } }"
                        class="!w-12 !h-12 md:!w-16 md:!h-16"
                        shape="circle"
                    />
                    <Avatar
                        v-else
                        :label="userName.charAt(0).toUpperCase()"
                        :style="getAvatarStyle(user.username)"
                        class="!w-12 !h-12 md:!w-16 md:!h-16"
                        shape="circle"
                    />
                </router-link>
                <div v-else class="flex-shrink-0">
                    <Avatar
                        :label="userName.charAt(0).toUpperCase()"
                        :style="getAvatarStyle(user.username)"
                        class="!w-12 !h-12 md:!w-16 md:!h-16"
                        shape="circle"
                    />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <UserName
                        v-if="!user.deletion_datetime"
                        :username="user.username"
                        :display-name="userName"
                        :to="profileRoute"
                        class="font-medium text-surface-900 dark:text-surface-0 text-base md:text-lg leading-tight hover:text-primary transition-colors block truncate"
                    />
                    <span v-else class="font-medium text-surface-500 text-base md:text-lg leading-tight block truncate">
                        {{ userName }}
                    </span>
                    <p class="text-surface-500 dark:text-surface-400 text-sm mt-0.5 truncate">
                        <span v-if="type === 1">{{ t('search.announce_card.looking_for_instrument', { instrument: instrument.toLowerCase() }) }}</span>
                        <span v-else>{{ instrument }}</span>
                    </p>
                </div>
                <!-- On a phone the note sits by the name; from md it keeps its place by the city. -->
                <AnnounceNoteButton v-if="isBelowMd" :note="note" :author-name="userName" :class="['shrink-0 self-start', TARGET_40]" />
            </div>

            <!-- Tags: one line on a phone, where three of them fit -->
            <div class="mt-2.5 md:mt-3 max-md:flex max-md:items-center max-md:gap-2 max-md:overflow-hidden">
                <span
                    v-for="(style, index) in visibleStyles"
                    :key="style.name"
                    class="inline-block px-3 py-1 text-xs font-medium rounded-full md:mr-2 md:mb-2"
                    :class="[
                        // On a phone's one line only the last tag gives way, so the others read whole.
                        index === visibleStyles.length - 1 ? 'max-md:min-w-0 max-md:truncate' : 'max-md:shrink-0',
                        isHighlighted(style)
                            ? 'bg-teal-100 text-teal-800 ring-1 ring-teal-300 dark:bg-teal-400/15 dark:text-teal-200 dark:ring-teal-400/40'
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300'
                    ]"
                >
                    {{ style.name }}<span v-if="isHighlighted(style)" class="sr-only">{{ ` ${t('search.announce_card.highlighted_style')}` }}</span>
                </span>
                <!-- A button, so a finger or a keyboard reads the rest too, not only a hovering mouse. -->
                <button
                    v-if="hiddenStyleCount > 0"
                    type="button"
                    :aria-label="t('search.announce_card.all_styles_aria', { count: hiddenStyleCount, styles: allStylesText })"
                    aria-haspopup="dialog"
                    :aria-expanded="isStylesOpen"
                    class="inline-block shrink-0 px-3 py-1 text-xs font-medium rounded-full md:mr-2 md:mb-2 bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 cursor-pointer"
                    @click="stylesPopover.toggle($event)"
                >
                    +{{ hiddenStyleCount }}
                </button>
                <Popover ref="stylesPopover" @show="isStylesOpen = true" @hide="isStylesOpen = false">
                    <p class="m-0 max-w-xs text-sm text-surface-700 dark:text-surface-200">{{ allStylesText }}</p>
                </Popover>
            </div>

            <!-- Location, then the spoken languages (#1198): one line on a phone -->
            <div class="flex flex-col max-md:flex-row max-md:items-center max-md:gap-1.5 mt-1 max-md:mt-2.5 text-sm text-surface-500 dark:text-surface-400">
            <div class="flex items-center justify-between gap-2 min-w-0">
                <span class="min-w-0 max-md:truncate">
                    <i class="pi pi-map-marker mr-1" />{{ location_name }}
                    <span v-if="distance" class="opacity-70">{{ ` ${separator()} ${formattedDistance}` }}</span>
                </span>
                <AnnounceNoteButton v-if="!isBelowMd" :note="note" :author-name="userName" class="shrink-0" />
            </div>
            <span v-if="languages.length > 0" class="md:hidden" aria-hidden="true">{{ separator() }}</span>
            <p v-if="languages.length > 0" class="shrink-0 md:mt-1 mb-0">
                <i class="pi pi-language mr-1" aria-hidden="true" /><span class="sr-only">{{ `${t('search.announce_card.languages_label')} ` }}</span>{{ languagesText }}
            </p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 mt-auto pt-3 md:pt-4">
                <button
                    :disabled="!!user.deletion_datetime"
                    @click="$router.push(profileRoute)"
                    class="flex-1 py-2.5 rounded-xl bg-surface-100 dark:bg-surface-800 max-md:bg-transparent max-md:border max-md:border-surface-300 max-md:dark:border-surface-600 text-surface-600 dark:text-surface-300 text-sm font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed enabled:hover:bg-surface-200 dark:enabled:hover:bg-surface-700 enabled:cursor-pointer"
                >
                    <i class="pi pi-user mr-2" />{{ t('search.announce_card.profile') }}
                </button>
                <button
                    v-if="!isOwnAnnounce"
                    :disabled="!!user.deletion_datetime"
                    @click="handleContact"
                    class="flex-1 py-2.5 rounded-xl bg-primary text-primary-contrast text-sm font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed enabled:hover:bg-primary-emphasis enabled:cursor-pointer"
                >
                    <i class="pi pi-envelope mr-2" />{{ t('search.announce_card.contact') }}
                </button>
                <ReportButton
                    v-if="announceId && !user.deletion_datetime"
                    target-type="announce"
                    :target-id="announceId"
                    :author-id="user.id"
                    :aria-label="t('search.announce_card.report', { name: userName })"
                    icon-only
                    :class="['self-center', TARGET_40]"
                />
            </div>
        </div>

        <SendMessageModal
            v-model:visible="showMessageModal"
            :selected-recipient="user"
            :contact-origin="announceId ? { musicianAnnounceId: announceId } : null"
            @sign-in-required="handleSignInRequired"
        />
        <AuthRequiredModal
            v-model:visible="showAuthModal"
            variant="contact"
            :musician-name="userName"
            :message="isDraftKept ? t('search.pending.contact_sign_in', { name: userName }) : ''"
        />
    </div>
</template>

<script setup lang="ts">
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Popover from 'primevue/popover'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AnnounceNoteButton from '../../components/Announce/AnnounceNoteButton.vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import ReportButton from '../../components/Report/ReportButton.vue'
import UserName from '../../components/User/UserName.vue'
import { useKeepContactDraft } from '../../composables/useContactDraft.js'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { displayName } from '../../helper/user/displayName.js'
import { separator } from '../../i18n/formatLocale.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { languageListLabel } from '../../utils/announceLanguages.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { MAX_VISIBLE_STYLES } from '../../utils/styles.js'

const props = defineProps({
  /** The announce itself, for « Signaler ». */
  announceId: { type: String, default: null },
  type: { type: Number, required: true },
  user: { type: Object, required: true },
  instrument: { type: String, required: true },
  styles: { type: Array, required: true },
  location_name: { type: String, required: true },
  distance: { type: [Number, String], default: null },
  /** What the author wrote on the announce, plain text (#1139). */
  note: { type: String, default: null },
  from: { type: String, default: null },
  /** Style names the search asked for (#1084): shown first and highlighted. */
  highlightedStyles: { type: Array, default: () => [] },
  /** Spoken language codes the author welcomes (#1198). */
  languages: { type: Array, default: () => [] }
})

const { t } = useI18n()
const userName = computed(() => displayName(props.user))

function isHighlighted(style) {
  return props.highlightedStyles.includes(style.name)
}

// Three tags fit one line of a phone's card.
const PHONE_VISIBLE_STYLES = 3
// The note and report icons are small text buttons; on the card they are a finger wide.
const TARGET_40 = '[&_.p-button]:!w-10 [&_.p-button]:!h-10'

const isBelowMd = useIsBelowMd()
const stylesPopover = ref(null)
const isStylesOpen = ref(false)
const visibleStyleLimit = computed(() =>
  isBelowMd.value ? PHONE_VISIBLE_STYLES : MAX_VISIBLE_STYLES
)
// The searched styles first, so the ones that match are among the visible tags.
const visibleStyles = computed(() =>
  [...props.styles]
    .sort((a, b) => Number(isHighlighted(b)) - Number(isHighlighted(a)))
    .slice(0, visibleStyleLimit.value)
)
const hiddenStyleCount = computed(() => Math.max(0, props.styles.length - visibleStyleLimit.value))
const allStylesText = computed(() => props.styles.map((s) => s.name).join(', '))
const languagesText = computed(() => languageListLabel(props.languages))

const userSecurityStore = useUserSecurityStore()
const keepContactDraft = useKeepContactDraft()
const showMessageModal = ref(false)
const showAuthModal = ref(false)
// Whether the message was kept for after sign-in, the only case the prompt may promise it.
const isDraftKept = ref(false)

const isOwnAnnounce = computed(() => userSecurityStore.userProfile?.id === props.user.id)

const profileRoute = computed(() => {
  const route = {
    name: 'app_user_public_profile',
    params: { username: props.user.username }
  }
  // Add from query param for contextual back navigation
  if (props.from) {
    route.query = { from: props.from }
  }
  return route
})

const formattedDistance = computed(() => {
  if (!props.distance) return ''
  const dist = Number(props.distance)
  return dist < 10 ? `${dist.toFixed(1)} km` : `${Math.round(dist)} km`
})

// A visitor writes too (#1306): « Envoyer » asks them to sign in, and the search page gives the message back.
function handleContact() {
  trackUmamiEvent('musician-result-contact')
  showMessageModal.value = true
}

function handleSignInRequired({ content }) {
  isDraftKept.value = keepContactDraft({
    announceId: props.announceId,
    recipientId: props.user.id,
    content
  })
  showAuthModal.value = true
}
</script>
