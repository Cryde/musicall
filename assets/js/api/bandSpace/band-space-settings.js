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
