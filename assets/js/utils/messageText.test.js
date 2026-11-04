import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { htmlToText } from './messageText.js'

/**
 * A message's text for « Copier le texte », from the HTML the API sends. Run with `npm test`.
 */

describe('htmlToText', () => {
  it('turns the line breaks the API adds back into new lines', () => {
    assert.equal(htmlToText('Salut&nbsp;!<br />\nOn répète jeudi ?'), 'Salut !\nOn répète jeudi ?')
    assert.equal(htmlToText('un<br>deux<br/>trois'), 'un\ndeux\ntrois')
  })

  it('decodes what the API escaped', () => {
    assert.equal(
      htmlToText('Rock &amp; roll &lt;3 &quot;live&quot; l&#039;été &#x27;ok&#x27;'),
      "Rock & roll <3 \"live\" l'été 'ok'"
    )
  })

  it('drops any other tag and keeps its text', () => {
    assert.equal(
      htmlToText('<a href="https://example.com">le lien</a> et <span class="mention">@lea</span>'),
      'le lien et @lea'
    )
  })

  it('leaves an entity it does not know as it is', () => {
    assert.equal(htmlToText('a &unknown; b'), 'a &unknown; b')
  })

  it('reads nothing as an empty text', () => {
    assert.equal(htmlToText(null), '')
    assert.equal(htmlToText(''), '')
  })
})
