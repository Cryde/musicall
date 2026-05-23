import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'

export const useBandSpaceSettingsStore = defineStore('bandSpaceSettings', () => {
  const members = ref([])
  const invitations = ref([])
  const isLoadingMembers = ref(false)
  const isLoadingInvitations = ref(false)
  const isInviting = ref(false)
  const isUpdatingRole = ref(false)
  const isKicking = ref(false)
  const isLeaving = ref(false)
  const isCancellingInvitation = ref(false)

  // Monotonic tokens to prevent stale members/invitations from a previous
  // bandSpace overwriting the current view when the user switches spaces
  // while a request is in flight.
  let membersLoadToken = 0
  let invitationsLoadToken = 0

  async function loadMembers(bandSpaceId) {
    const token = ++membersLoadToken
    isLoadingMembers.value = true
    members.value = []
    try {
      const data = await bandSpaceSettingsApi.getMembers(bandSpaceId)
      if (token !== membersLoadToken) return
      members.value = data
    } finally {
      if (token === membersLoadToken) {
        isLoadingMembers.value = false
      }
    }
  }

  async function loadInvitations(bandSpaceId) {
    const token = ++invitationsLoadToken
    isLoadingInvitations.value = true
    invitations.value = []
    try {
      const data = await bandSpaceSettingsApi.getInvitations(bandSpaceId)
      if (token !== invitationsLoadToken) return
      invitations.value = data
    } finally {
      if (token === invitationsLoadToken) {
        isLoadingInvitations.value = false
      }
    }
  }

  async function invite(bandSpaceId, identifier) {
    isInviting.value = true
    try {
      const invitation = await bandSpaceSettingsApi.createInvitation(bandSpaceId, identifier)
      invitations.value = [invitation, ...invitations.value]
      return invitation
    } finally {
      isInviting.value = false
    }
  }

  async function cancelInvitation(bandSpaceId, invitationId) {
    isCancellingInvitation.value = true
    try {
      await bandSpaceSettingsApi.cancelInvitation(bandSpaceId, invitationId)
      invitations.value = invitations.value.filter((i) => i.id !== invitationId)
    } finally {
      isCancellingInvitation.value = false
    }
  }

  async function updateRole(bandSpaceId, memberId, role) {
    isUpdatingRole.value = true
    try {
      const updated = await bandSpaceSettingsApi.updateMemberRole(bandSpaceId, memberId, role)
      const index = members.value.findIndex((m) => m.id === memberId)
      if (index !== -1) {
        members.value[index] = updated
      }
      return updated
    } finally {
      isUpdatingRole.value = false
    }
  }

  async function kickMember(bandSpaceId, memberId) {
    isKicking.value = true
    try {
      await bandSpaceSettingsApi.kickMember(bandSpaceId, memberId)
      members.value = members.value.filter((m) => m.id !== memberId)
    } finally {
      isKicking.value = false
    }
  }

  async function leave(bandSpaceId) {
    isLeaving.value = true
    try {
      await bandSpaceSettingsApi.leaveBandSpace(bandSpaceId)
    } finally {
      isLeaving.value = false
    }
  }

  function clear() {
    members.value = []
    invitations.value = []
  }

  return {
    members: readonly(members),
    invitations: readonly(invitations),
    isLoadingMembers: readonly(isLoadingMembers),
    isLoadingInvitations: readonly(isLoadingInvitations),
    isInviting: readonly(isInviting),
    isUpdatingRole: readonly(isUpdatingRole),
    isKicking: readonly(isKicking),
    isLeaving: readonly(isLeaving),
    isCancellingInvitation: readonly(isCancellingInvitation),
    loadMembers,
    loadInvitations,
    invite,
    cancelInvitation,
    updateRole,
    kickMember,
    leave,
    clear
  }
})
