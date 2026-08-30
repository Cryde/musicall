/**
 * These pin the sentences the bulk bar shows under a disabled button against the ones the endpoints
 * throw. The two drifting apart is the whole failure this guards: the tooltip would explain one
 * refusal and the server would answer with another.
 *
 * The file names are the ones BandSpaceFileBulkDeleteTest and BandSpaceFileBulkRestoreTest use, so
 * both layers can be diffed by eye.
 *
 * Run with `npm test`.
 */

import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  attachedFiles,
  attachmentReason,
  deleteBlockedReason,
  deleteOwnershipReason,
  filesNotOwnedBy,
  restoreOwnershipReason
} from './fileActions.js'

const ALICE = 'user-alice'
const BOB = 'user-bob'

function file(overrides = {}) {
  return {
    id: 'file-1',
    original_name: 'la-mienne.wav',
    created_by: { id: ALICE },
    attachments: [],
    ...overrides
  }
}

describe('filesNotOwnedBy', () => {
  it('names every file of the selection the member did not upload, in selection order', () => {
    const selection = [
      file({ original_name: 'la-mienne.wav', created_by: { id: BOB } }),
      file({ original_name: 'mix-final.wav' }),
      file({ original_name: 'contrat.pdf' })
    ]

    assert.deepEqual(filesNotOwnedBy(selection, BOB, false), ['mix-final.wav', 'contrat.pdf'])
  })

  it('blocks nothing for an admin', () => {
    const selection = [file({ created_by: { id: BOB } })]

    assert.deepEqual(filesNotOwnedBy(selection, ALICE, true), [])
  })

  // The profile arrives with the session rather than with the list, so it can still be missing.
  it('blocks everything when the current member is not known yet', () => {
    assert.deepEqual(filesNotOwnedBy([file()], null, false), ['la-mienne.wav'])
  })

  it('blocks nothing on an empty selection', () => {
    assert.deepEqual(filesNotOwnedBy([], ALICE, false), [])
  })
})

describe('deleteOwnershipReason and restoreOwnershipReason', () => {
  const selection = [
    file({ original_name: 'mix-final.wav', created_by: { id: BOB } }),
    file({ original_name: 'contrat.pdf', created_by: { id: BOB } })
  ]

  it('reads exactly as the delete endpoint refuses', () => {
    assert.equal(
      deleteOwnershipReason(selection, ALICE, false),
      'Seul le créateur ou un administrateur peut supprimer ces fichiers : mix-final.wav, contrat.pdf'
    )
  })

  it('reads exactly as the restore endpoint refuses', () => {
    assert.equal(
      restoreOwnershipReason(selection, ALICE, false),
      'Seul le créateur ou un administrateur peut restaurer ces fichiers : mix-final.wav, contrat.pdf'
    )
  })

  it('says nothing when nothing blocks', () => {
    assert.equal(deleteOwnershipReason([file()], ALICE, false), null)
    assert.equal(restoreOwnershipReason([file()], ALICE, false), null)
  })
})

describe('attachedFiles', () => {
  it('keeps only the files something still points at', () => {
    const selection = [
      file({ original_name: 'libre.wav' }),
      file({ original_name: 'devis.pdf', attachments: [{ source_type: 'task' }] })
    ]

    assert.deepEqual(
      attachedFiles(selection).map((f) => f.original_name),
      ['devis.pdf']
    )
  })

  it('treats a missing attachments array as unattached', () => {
    assert.deepEqual(attachedFiles([{ original_name: 'x.wav' }]), [])
  })
})

describe('attachmentReason', () => {
  it('reads exactly as the endpoint refuses one file', () => {
    const selection = [
      file({ original_name: 'facture.pdf', attachments: [{ source_type: 'finance' }] })
    ]

    assert.equal(
      attachmentReason(selection),
      "1 fichier sélectionné est attaché à une entrée financière. Détachez-le d'abord depuis la ressource concernée."
    )
  })

  // Sorted source types, so the client and the server enumerate them in the same order: 'note'
  // sorts before 'task', which is why the sentence names the note first.
  it('reads exactly as the endpoint refuses several, sources named in the same order', () => {
    const selection = [
      file({ original_name: 'libre.wav' }),
      file({ original_name: 'devis.pdf', attachments: [{ source_type: 'task' }] }),
      file({ original_name: 'paroles.pdf', attachments: [{ source_type: 'note' }] })
    ]

    assert.equal(
      attachmentReason(selection),
      "2 fichiers sélectionnés sont attachés à une note et une tâche. Détachez-les d'abord depuis les ressources concernées."
    )
  })

  it('names a repeated source once, like the server does', () => {
    const selection = [
      file({ original_name: 'a.pdf', attachments: [{ source_type: 'task' }] }),
      file({ original_name: 'b.pdf', attachments: [{ source_type: 'task' }] })
    ]

    assert.equal(
      attachmentReason(selection),
      "2 fichiers sélectionnés sont attachés à une tâche. Détachez-les d'abord depuis les ressources concernées."
    )
  })

  it('says nothing when nothing is attached', () => {
    assert.equal(attachmentReason([file()]), null)
    assert.equal(attachmentReason([]), null)
  })
})

describe('deleteBlockedReason', () => {
  // Ownership is the refusal the server reaches first, so the member is never told to detach files
  // only to be refused again for a reason nobody mentioned.
  it('reports ownership before attachment when both block', () => {
    const selection = [
      file({
        original_name: 'mix-final.wav',
        created_by: { id: BOB },
        attachments: [{ source_type: 'task' }]
      })
    ]

    assert.equal(
      deleteBlockedReason(selection, ALICE, false),
      'Seul le créateur ou un administrateur peut supprimer ces fichiers : mix-final.wav'
    )
  })

  it('falls through to attachment when ownership is fine', () => {
    const selection = [file({ original_name: 'devis.pdf', attachments: [{ source_type: 'task' }] })]

    assert.equal(
      deleteBlockedReason(selection, ALICE, false),
      "1 fichier sélectionné est attaché à une tâche. Détachez-le d'abord depuis la ressource concernée."
    )
  })

  it('says nothing when the selection is clean', () => {
    assert.equal(deleteBlockedReason([file()], ALICE, false), null)
  })
})
