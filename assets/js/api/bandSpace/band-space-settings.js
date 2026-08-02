/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getMembers(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_members_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  updateMemberRole(bandSpaceId, memberId, role) {
    return axios
      .patch(
        Routing.generate('api_band_space_members_patch', { bandSpaceId, id: memberId }),
        { role },
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * Separate from updateMemberRole because the server enforces a different rule: a member may
   * edit their own profile, an admin anyone's, whereas roles are admin only.
   */
  updateMemberProfile(bandSpaceId, memberId, { stageName, instrumentIds }) {
    return axios
      .patch(
        Routing.generate('api_band_space_member_profile_patch', { bandSpaceId, id: memberId }),
        { stage_name: stageName, instrument_ids: instrumentIds },
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  kickMember(bandSpaceId, memberId) {
    return axios
      .delete(Routing.generate('api_band_space_members_delete', { bandSpaceId, id: memberId }))
      .catch(handleApiError)
  },

  leaveBandSpace(bandSpaceId) {
    return axios
      .post(
        Routing.generate('api_band_space_leave', { bandSpaceId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  // Schedules the deletion 30 days out, it does not delete: restoreBandSpace cancels it until then.
  scheduleBandSpaceDeletion(bandSpaceId) {
    return axios
      .delete(Routing.generate('api_band_spaces_delete', { id: bandSpaceId }))
      .catch(handleApiError)
  },

  restoreBandSpace(bandSpaceId) {
    return axios
      .post(
        Routing.generate('api_band_space_restore', { bandSpaceId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  getInvitations(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_invitations_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createInvitation(bandSpaceId, identifier) {
    return axios
      .post(
        Routing.generate('api_band_space_invitations_post', { bandSpaceId }),
        { identifier },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  cancelInvitation(bandSpaceId, invitationId) {
    return axios
      .delete(
        Routing.generate('api_band_space_invitations_delete', { bandSpaceId, id: invitationId })
      )
      .catch(handleApiError)
  },

  getInvitationInfo(token) {
    return axios
      .get(Routing.generate('api_band_space_invitations_info', { token }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  acceptInvitation(token) {
    return axios
      .post(
        Routing.generate('api_band_space_invitations_accept', { token }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  declineInvitation(token) {
    return axios
      .post(
        Routing.generate('api_band_space_invitations_decline', { token }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  }
}
