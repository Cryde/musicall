<template>
  <div class="flex flex-col gap-4 md:gap-6">
    <!-- Invite form, admin only: the server refuses an invitation sent by a plain member -->
    <div v-if="isAdmin" class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 md:p-6">
      <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4">
        {{ t('band_space.members.invite_title') }}
      </h3>
      <form @submit.prevent="handleInvite" class="flex gap-2 md:gap-3">
        <InputText
          v-model="inviteIdentifier"
          :placeholder="t('band_space.members.invite_placeholder')"
          :aria-label="t('band_space.members.invite_placeholder')"
          class="min-w-0 flex-1 max-md:!text-[0.9375rem]"
          :disabled="settingsStore.isInviting"
        />
        <Button
          type="submit"
          :label="t('band_space.members.invite')"
          icon="pi pi-send"
          :loading="settingsStore.isInviting"
          :disabled="!inviteIdentifier.trim()"
        />
      </form>
      <small v-if="inviteError" class="text-red-500 mt-2 block">{{ inviteError }}</small>
      <small v-if="inviteSuccess" class="text-green-600 mt-2 block">{{ inviteSuccess }}</small>
    </div>

    <!-- Pending invitations -->
    <div v-if="settingsStore.invitations.length > 0" class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 md:p-6">
      <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4">
        {{ t('band_space.dashboard.invitations.title') }}
      </h3>
      <div class="flex flex-col gap-3">
        <div
          v-for="invitation in settingsStore.invitations"
          :key="invitation.id"
          class="flex items-center justify-between gap-2 p-3 rounded-lg bg-surface-50 dark:bg-surface-800 max-md:gap-3 max-md:bg-transparent max-md:p-0 dark:max-md:bg-transparent"
        >
          <span
            v-if="isBelowMd"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
            aria-hidden="true"
          >
            <i class="pi pi-envelope text-sm" />
          </span>
          <!-- On a phone the address keeps its own line and the expiry goes under it. -->
          <div class="min-w-0 max-md:flex-1">
            <span class="text-sm font-medium text-surface-800 dark:text-surface-100 max-md:block max-md:truncate max-md:text-[0.9375rem] max-md:font-semibold">
              {{ invitationTarget(invitation) }}
            </span>
            <span class="text-xs text-surface-500 dark:text-surface-400 ml-2 max-md:ml-0 max-md:block max-md:text-[0.8125rem] max-md:text-surface-600 dark:max-md:text-surface-300">
              {{ t('band_space.members.expires_on', { date: formatDate(invitation.expiration_datetime) }) }}
            </span>
          </div>
          <Button
            icon="pi pi-times"
            text
            rounded
            severity="danger"
            size="small"
            class="shrink-0 max-md:h-11! max-md:w-11!"
            v-tooltip.top="t('band_space.members.cancel_invitation')"
            :aria-label="t('band_space.members.cancel_invitation')"
            :loading="settingsStore.isCancellingInvitation"
            @click="handleCancelInvitation(invitation)"
          />
        </div>
      </div>
    </div>

    <!-- Members list -->
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 md:p-6 max-md:px-0 max-md:pb-1">
      <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100 mb-4 max-md:mb-1 max-md:flex max-md:items-center max-md:justify-between max-md:px-4">
        {{ t('band_space.settings.section.members') }}
        <span v-if="isBelowMd" class="text-[0.8125rem] font-normal text-surface-500 dark:text-surface-400" aria-hidden="true">
          {{ settingsStore.members.length }}
        </span>
      </h3>

      <ProgressSpinner v-if="settingsStore.isLoadingMembers" class="flex justify-center" />

      <!-- A phone lists the members flat, one line each, with one button that names what it does. -->
      <ul v-else-if="isBelowMd" class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
        <li v-for="member in settingsStore.members" :key="member.id" class="flex min-h-15 items-center gap-3 py-2 pr-1 pl-4">
          <MemberAvatar :member="member" class="!h-9 !w-9" />
          <div class="min-w-0 flex-1">
            <p class="m-0 truncate text-[0.9375rem] font-semibold text-surface-900 dark:text-surface-0">
              {{ member.display_name }}
              <span
                v-if="userHandle(member.username, member.display_name, member.is_deleted)"
                class="font-normal text-surface-500 dark:text-surface-400"
              >({{ member.username }})</span>
              <span v-if="isMe(member)" class="ml-1 font-normal text-surface-500 dark:text-surface-400">
                {{ t('band_space.members.you') }}
              </span>
            </p>
            <p class="m-0 truncate text-[0.8125rem] text-surface-600 dark:text-surface-300">{{ memberSummary(member) }}</p>
          </div>
          <Button
            v-if="canManage(member)"
            icon="pi pi-ellipsis-v"
            text
            rounded
            severity="secondary"
            class="h-11! w-11! shrink-0"
            aria-haspopup="dialog"
            :aria-label="t('band_space.settings.phone.member_actions', { name: member.display_name })"
            @click="openMemberActions(member)"
          />
          <Button
            v-else-if="canEditProfile(member)"
            icon="pi pi-pencil"
            text
            rounded
            severity="secondary"
            class="h-11! w-11! shrink-0"
            :aria-label="t('band_space.members.edit_profile_aria', { name: member.display_name })"
            @click="openProfileDialog(member)"
          />
        </li>
      </ul>

      <div v-else class="flex flex-col gap-3">
        <div
          v-for="member in settingsStore.members"
          :key="member.id"
          class="flex items-center justify-between gap-2 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
        >
          <div class="flex min-w-0 items-center gap-3">
            <MemberAvatar :member="member" />
            <div class="min-w-0 [overflow-wrap:anywhere]">
              <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
                {{ member.display_name }}
              </span>
              <!-- Shown only when it differs, so the row says who this is without repeating
                   itself for the members who never set a stage name. -->
              <span
                v-if="userHandle(member.username, member.display_name, member.is_deleted)"
                class="text-xs text-surface-500 dark:text-surface-400 ml-1"
              >
                ({{ member.username }})
              </span>
              <span v-if="isMe(member)" class="text-xs text-surface-400 ml-1">{{ t('band_space.members.you') }}</span>
              <p
                v-if="member.instruments.length > 0"
                class="text-xs text-surface-600 dark:text-surface-300"
              >
                {{ member.instruments.map((instrument) => instrument.name).join(', ') }}
              </p>
            </div>
            <Tag
              :value="member.role === 'admin' ? t('band_space.members.role_admin') : t('band_space.members.role_member')"
              :severity="member.role === 'admin' ? 'warn' : 'info'"
              class="text-xs"
            />
          </div>

          <div class="flex shrink-0 gap-1">
            <!-- Your own profile, or anyone's if you are an admin. The same rule the server
                 enforces, so the button is not offered where the request would be refused. -->
            <Button
              v-if="canEditProfile(member)"
              icon="pi pi-pencil"
              text
              rounded
              size="small"
              v-tooltip.top="t('band_space.member_profile.header')"
              :aria-label="t('band_space.members.edit_profile_aria', { name: member.display_name })"
              @click="openProfileDialog(member)"
            />
            <!-- Promoting, demoting and excluding are admin only server side, so a member is not
                 offered a button that would come back as a red toast. -->
            <template v-if="canManage(member)">
            <Button
              v-if="member.role === 'user'"
              icon="pi pi-arrow-up"
              text
              rounded
              size="small"
              v-tooltip.top="t('band_space.members.promote')"
              :aria-label="t('band_space.members.promote')"
              :loading="settingsStore.isUpdatingRole"
              @click="handlePromote(member)"
            />
            <Button
              v-if="member.role === 'admin'"
              icon="pi pi-arrow-down"
              text
              rounded
              size="small"
              v-tooltip.top="t('band_space.members.demote')"
              :aria-label="t('band_space.members.demote')"
              :loading="settingsStore.isUpdatingRole"
              @click="handleDemote(member)"
            />
            <Button
              icon="pi pi-user-minus"
              text
              rounded
              severity="danger"
              size="small"
              v-tooltip.top="t('band_space.members.kick')"
              :aria-label="t('band_space.members.kick')"
              :loading="settingsStore.isKicking"
              @click="handleKick(member)"
            />
            </template>
          </div>
        </div>
      </div>
    </div>

    <BottomSheet
      v-model:visible="isMemberSheetOpen"
      :title="memberSheetTarget?.display_name ?? ''"
      @after-hide="runPendingMemberAction"
    >
      <template v-if="memberSheetTarget" #header>
        <span class="flex min-w-0 items-center gap-3">
          <MemberAvatar :member="memberSheetTarget" />
          <span class="min-w-0">
            <span class="block truncate font-semibold text-surface-900 dark:text-surface-0">
              {{ memberSheetTarget.display_name }}
            </span>
            <span class="block truncate text-xs text-surface-600 dark:text-surface-300">
              {{ memberSummary(memberSheetTarget) }}
            </span>
          </span>
        </span>
      </template>
      <template v-if="memberSheetTarget">
        <SheetAction icon="pi-pencil" @click="runMemberAction(openProfileDialog)">
          {{ t('band_space.member_profile.header') }}
        </SheetAction>
        <SheetAction
          v-if="memberSheetTarget.role === 'user'"
          icon="pi-arrow-up"
          :disabled="settingsStore.isUpdatingRole"
          @click="runMemberAction(handlePromote)"
        >
          {{ t('band_space.members.promote') }}
        </SheetAction>
        <SheetAction
          v-else
          icon="pi-arrow-down"
          :disabled="settingsStore.isUpdatingRole"
          @click="runMemberAction(handleDemote)"
        >
          {{ t('band_space.members.demote') }}
        </SheetAction>
        <SheetAction icon="pi-user-minus" danger @click="runMemberAction(handleKick)">
          {{ t('band_space.members.kick') }}
        </SheetAction>
      </template>
    </BottomSheet>

    <MemberProfileDialog
      v-model:visible="profileDialogOpen"
      :band-space-id="bandSpaceId"
      :member="profileTarget"
      :is-self="!!profileTarget && isMe(profileTarget)"
    />

    <!-- Leave -->
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 md:p-6">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <h3 class="text-base font-semibold text-surface-800 dark:text-surface-100">
            {{ t('band_space.members.leave_title') }}
          </h3>
          <p v-if="isOnlyAdmin" class="text-xs text-surface-500 mt-1">
            {{ t('band_space.members.only_admin') }}
          </p>
        </div>
        <Button
          :label="t('band_space.members.leave')"
          severity="danger"
          outlined
          class="shrink-0"
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
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useBandSpaceLiveRefresh } from '../../../composables/useBandSpaceLiveRefresh.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { intlLocale } from '../../../i18n/formatLocale.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { userHandle } from '../../../utils/userHandle.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import MemberAvatar from './MemberAvatar.vue'
import MemberProfileDialog from './MemberProfileDialog.vue'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
const settingsStore = useBandSpaceSettingsStore()
const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()
// The role comes from the space rather than from the roster, like the parent view and the « Zone de
// danger » do: the space list is loaded before this page renders, so the gate is settled on first
// paint and the decision below on whether to ask for the invitations cannot be taken too early.
const { isAdmin } = useBandSpaceNavigation()
// Wipe previous space's members/invitations synchronously before first
// render to avoid flashing A's roster when switching to B's Settings.
settingsStore.clear()

const bandSpaceId = route.params.id
const inviteIdentifier = ref('')
const inviteError = ref('')
const inviteSuccess = ref('')

const isMe = (member) => member.username === userSecurityStore.user?.username

const profileDialogOpen = ref(false)
const profileTarget = ref(null)

function canEditProfile(member) {
  return isMe(member) || isAdmin.value
}

// Promoting, demoting and excluding someone else: admin only, server side too.
function canManage(member) {
  return isAdmin.value && !isMe(member)
}

function memberSummary(member) {
  const role =
    member.role === 'admin'
      ? t('band_space.members.role_admin')
      : t('band_space.members.role_member')
  const instruments = member.instruments.map((instrument) => instrument.name).join(', ')

  return instruments ? `${role} · ${instruments}` : role
}

const isBelowMd = useIsBelowMd()
const isMemberSheetOpen = ref(false)
const memberSheetTarget = ref(null)

function openMemberActions(member) {
  memberSheetTarget.value = member
  isMemberSheetOpen.value = true
}

// The action waits for the sheet to be gone: the sheet unlocks the page scroll as it leaves, which would
// undo the lock of a dialog or confirmation opened meanwhile.
let pendingMemberAction = null

function runMemberAction(action) {
  pendingMemberAction = action
  isMemberSheetOpen.value = false
}

function runPendingMemberAction() {
  const action = pendingMemberAction
  pendingMemberAction = null
  action?.(memberSheetTarget.value)
}

function openProfileDialog(member) {
  profileTarget.value = member
  profileDialogOpen.value = true
}

const adminCount = computed(() => settingsStore.members.filter((m) => m.role === 'admin').length)
const isOnlyAdmin = computed(() => {
  const me = settingsStore.members.find((m) => isMe(m))
  return me?.role === 'admin' && adminCount.value === 1
})

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString(intlLocale(), {
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
    inviteSuccess.value = t('band_space.members.invite_sent', { target: inviteIdentifier.value })
    inviteIdentifier.value = ''
  } catch (e) {
    inviteError.value = e.message
  }
}

// An invitation made by username carries no email (#1119): it is named by that username instead.
function invitationTarget(invitation) {
  if (invitation.email) return invitation.email
  // The account behind an invitation by username can be gone, leaving neither
  return invitation.invited_username
    ? `@${invitation.invited_username}`
    : t('band_space.members.unknown_user')
}

function handleCancelInvitation(invitation) {
  confirm.require({
    message: t('band_space.members.cancel_invitation_message', {
      target: invitationTarget(invitation)
    }),
    header: t('band_space.members.cancel_invitation_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space.members.no'),
    acceptLabel: t('band_space.members.yes_cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await settingsStore.cancelInvitation(bandSpaceId, invitation.id)
        toast.add({
          severity: 'success',
          summary: t('band_space.members.invitation_cancelled'),
          life: 3000
        })
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
      summary: t('band_space.members.promoted', { username: member.username }),
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
      summary: t('band_space.members.demoted', { username: member.username }),
      life: 3000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function handleKick(member) {
  confirm.require({
    message: t('band_space.members.kick_message', { username: member.username }),
    header: t('band_space.members.kick_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space.common.cancel'),
    acceptLabel: t('band_space.members.kick'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await settingsStore.kickMember(bandSpaceId, member.id)
        toast.add({
          severity: 'success',
          summary: t('band_space.members.kicked', { username: member.username }),
          life: 3000
        })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}

function handleLeave() {
  confirm.require({
    message: t('band_space.members.leave_message'),
    header: t('band_space.members.leave_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space.common.cancel'),
    acceptLabel: t('band_space.members.leave'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await settingsStore.leave(bandSpaceId)
        await bandSpaceStore.loadMyBandSpaces()
        router.replace({ name: BAND_SPACE_ROUTES.INDEX })
        toast.add({ severity: 'info', summary: t('band_space.members.left'), life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}

useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['settings'],
  refresh: () => {
    settingsStore.loadMembers(bandSpaceId, { quiet: true }).catch(() => {})
    if (isAdmin.value) settingsStore.loadInvitations(bandSpaceId, { quiet: true }).catch(() => {})
  }
})

onMounted(async () => {
  try {
    await settingsStore.loadMembers(bandSpaceId)
    // Listing the invitations is admin only server side, so asking for them as a plain member would
    // buy nothing but a 403.
    if (isAdmin.value) {
      await settingsStore.loadInvitations(bandSpaceId)
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
})

onUnmounted(() => {
  settingsStore.clear()
})
</script>
