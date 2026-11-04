import assert from 'node:assert/strict'
import { after, afterEach, before, describe, it } from 'node:test'
import { i18n } from '../../../i18n/index.js'
import { activitySentence } from './activitySentences.js'

const invitation = (type, payload) => ({ module: 'settings', type, payload })

describe('invitation activity sentences', () => {
  it('name an invitation by the address the inviter typed', () => {
    assert.equal(
      activitySentence(invitation('invitation_sent', { email: 'a***@example.com' })),
      'a invité a***@example.com'
    )
  })

  it('name an invitation made by username by that username, never an address (#1119)', () => {
    assert.equal(
      activitySentence(invitation('invitation_sent', { invited_username: 'bassiste' })),
      'a invité @bassiste'
    )
    assert.equal(
      activitySentence(invitation('invitation_declined', { invited_username: 'bassiste' })),
      "a refusé l'invitation pour @bassiste"
    )
  })

  it('fall back when the payload names nobody', () => {
    assert.equal(
      activitySentence(invitation('invitation_expired', {})),
      "l'invitation pour un utilisateur a expiré"
    )
  })
})

const agenda = (type, payload) => ({ module: 'agenda', type, payload })

// West of UTC is where reading a bare day as an instant would show the day before.
for (const timeZone of ['Europe/Paris', 'America/Martinique']) {
  describe(`agenda date sentences in ${timeZone} (#1162)`, () => {
    const original = process.env.TZ
    before(() => {
      process.env.TZ = timeZone
    })
    after(() => {
      process.env.TZ = original
    })

    it('name a cancelled date by the day members saw, not its UTC key', () => {
      assert.equal(
        activitySentence(
          agenda('occurrence_cancelled', {
            title: 'Test minuit',
            occurrence_date: '2026-10-11',
            occurrence_civil_date: '2026-10-12'
          })
        ),
        'a annulé la date du 12 octobre 2026 de « Test minuit »'
      )
    })

    it('fall back to the UTC key on a cancellation recorded before the civil date existed', () => {
      assert.equal(
        activitySentence(
          agenda('occurrence_cancelled', { title: 'Répétition', occurrence_date: '2026-06-15' })
        ),
        'a annulé la date du 15 juin 2026 de « Répétition »'
      )
    })

    it('spell out the day a series was truncated from', () => {
      assert.equal(
        activitySentence(
          agenda('series_truncated', { title: 'Test minuit', from_occurrence_date: '2026-10-13' })
        ),
        'a tronqué la série « Test minuit » à partir du 13 octobre 2026'
      )
    })

    it('keep a placeholder when the payload has no date', () => {
      assert.equal(
        activitySentence(agenda('series_truncated', { title: 'Test minuit' })),
        'a tronqué la série « Test minuit » à partir du ?'
      )
    })
  })
}

const finance = (type, payload) => ({ module: 'finance', type, payload })

describe('finance activity sentences', () => {
  it('write amounts in euros the French way', () => {
    assert.equal(
      activitySentence(finance('entry_created', { label: 'Cachet', amount: 123450 })),
      "a créé l'entrée « Cachet » (1\u202f234,50\u00a0€)"
    )
    assert.equal(
      activitySentence(finance('entry_amount_changed', { from: 5000, to: 7500 })),
      'a changé le montant de 50,00\u00a0€ à 75,00\u00a0€'
    )
  })

  describe('with a payload that names nothing', () => {
    afterEach(() => {
      i18n.global.locale.value = 'fr'
    })

    it('stand in for the name with an em dash in French', () => {
      assert.equal(activitySentence(finance('entry_deleted', {})), "a supprimé l'entrée « — »")
    })

    // The wording comes from the catalogue: only the stand-in is this module's.
    it('stand in for the name and the amount with a hyphen in English', () => {
      i18n.global.locale.value = 'en'
      const sentences = [
        activitySentence(finance('entry_deleted', {})),
        activitySentence(finance('entry_amount_changed', { from: null, to: 7500 }))
      ]

      for (const sentence of sentences) {
        assert.ok(!sentence.includes('—'), sentence)
        assert.ok(sentence.includes('-'), sentence)
      }
    })
  })
})

const setlist = (type, payload) => ({ module: 'setlist', type, payload })

describe('lyrics activity sentences (#1260)', () => {
  it('name the song only when the lyrics were written for one', () => {
    assert.equal(
      activitySentence(setlist('lyrics_added', { title: 'Idée', song_title: null })),
      'a écrit les paroles « Idée »'
    )
    assert.equal(
      activitySentence(setlist('lyrics_added', { title: 'Idée', song_title: 'Enfin' })),
      'a écrit les paroles « Idée » pour la chanson « Enfin »'
    )
  })

  it('name both the lyrics and the song on a link and an unlink', () => {
    assert.equal(
      activitySentence(setlist('lyrics_linked', { title: 'Idée', song_title: 'Enfin' })),
      'a lié les paroles « Idée » à la chanson « Enfin »'
    )
    assert.equal(
      activitySentence(setlist('lyrics_unlinked', { title: 'Idée', song_title: 'Enfin' })),
      'a délié les paroles « Idée » de la chanson « Enfin »'
    )
  })

  it('read the edit, archive and restore by title', () => {
    assert.deepEqual(
      ['lyrics_updated', 'lyrics_archived', 'lyrics_restored'].map((type) =>
        activitySentence(setlist(type, { title: 'Idée' }))
      ),
      [
        'a modifié les paroles « Idée »',
        'a mis les paroles « Idée » à la corbeille',
        'a restauré les paroles « Idée »'
      ]
    )
  })
})

const file = (type, payload) => ({ module: 'file', type, payload })

describe('file and folder activity sentences', () => {
  it('name the file when the payload carries its name', () => {
    assert.deepEqual(
      ['uploaded', 'archived', 'restored', 'purged'].map((type) =>
        activitySentence(file(type, { original_name: 'Fiche technique.pdf' }))
      ),
      [
        'a téléversé le fichier « Fiche technique.pdf »',
        'a archivé le fichier « Fiche technique.pdf »',
        'a restauré le fichier « Fiche technique.pdf »',
        'a supprimé définitivement le fichier « Fiche technique.pdf »'
      ]
    )
  })

  it('read a rename from the old name to the new one', () => {
    assert.equal(
      activitySentence(file('renamed', { from: 'demo.mp3', to: 'Nuit blanche.mp3' })),
      'a renommé le fichier « demo.mp3 » en « Nuit blanche.mp3 »'
    )
  })

  it('name the folder a file moved to, and say nothing of the root', () => {
    assert.equal(
      activitySentence(file('moved', { to_folder_id: 'f', to_folder_name: 'Concerts' })),
      'a déplacé un fichier vers « Concerts »'
    )
    assert.equal(
      activitySentence(file('moved', { to_folder_id: null, to_folder_name: null })),
      'a déplacé un fichier'
    )
  })

  it('name the tag and the version number', () => {
    assert.equal(
      activitySentence(file('tagged', { tag_name: 'Live' })),
      'a ajouté le tag « Live » à un fichier'
    )
    assert.equal(
      activitySentence(file('untagged', { tag_name: 'Live' })),
      "a retiré le tag « Live » d'un fichier"
    )
    assert.equal(
      activitySentence(file('version_added', { version_number: 3 })),
      "a ajouté la version 3 d'un fichier"
    )
  })

  it('read the version a rollback went back to, as the server writes it', () => {
    assert.equal(
      activitySentence(file('rolled_back', { from_version_number: 3, to_version_number: 1 })),
      "est revenu à la version 1 d'un fichier"
    )
  })

  it('word the share links without a file name, which their payload does not carry', () => {
    assert.deepEqual(
      ['shared', 'share_revoked', 'public_accessed'].map((type) =>
        activitySentence(file(type, {}))
      ),
      [
        'a créé un lien de partage pour un fichier',
        'a révoqué un lien de partage',
        "le lien de partage d'un fichier a été consulté"
      ]
    )
  })

  it('name what a file was attached to or detached from, with its label when there is one', () => {
    assert.equal(
      activitySentence(
        file('attached', { source_type: 'task', source_label: 'Imprimer les setlists' })
      ),
      'a attaché un fichier à la tâche « Imprimer les setlists »'
    )
    assert.equal(
      activitySentence(file('detached', { source_type: 'task' })),
      'a détaché un fichier de la tâche'
    )
    assert.equal(
      activitySentence(file('attached', { source_type: 'robot' })),
      'a attaché un fichier'
    )
  })

  it('credit a deleted source, not a detach, when a file was released by it', () => {
    assert.equal(
      activitySentence(file('source_deleted', { source_type: 'task', source_label: 'Flyers' })),
      'a supprimé la tâche « Flyers », son fichier a été détaché'
    )
    assert.equal(
      activitySentence(file('source_deleted', {})),
      'a supprimé la ressource associée, son fichier a été détaché'
    )
  })

  it('word the folder actions', () => {
    assert.equal(
      activitySentence(file('folder_created', { name: 'Concerts', parent_id: null })),
      'a créé le dossier « Concerts »'
    )
    assert.equal(
      activitySentence(file('folder_renamed', { from: 'Live', to: 'Concerts' })),
      'a renommé le dossier « Live » en « Concerts »'
    )
    assert.equal(
      activitySentence(file('folder_moved', { from_parent_id: 'a', to_parent_id: null })),
      'a déplacé un dossier'
    )
    assert.equal(
      activitySentence(
        file('folder_archived', { name: 'Concerts', strategy: 'archive', file_count: 4 })
      ),
      'a supprimé le dossier « Concerts »'
    )
  })

  it('never fall back to the raw action name', () => {
    for (const type of [
      'uploaded',
      'archived',
      'restored',
      'purged',
      'renamed',
      'moved',
      'tagged',
      'untagged',
      'version_added',
      'rolled_back',
      'shared',
      'share_revoked',
      'public_accessed',
      'attached',
      'detached',
      'source_deleted',
      'folder_created',
      'folder_renamed',
      'folder_moved',
      'folder_archived'
    ]) {
      assert.doesNotMatch(activitySentence(file(type, {})), /a effectué l'action/, type)
    }
  })
})
