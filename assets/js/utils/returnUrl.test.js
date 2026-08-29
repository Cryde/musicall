import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isSafeReturnUrl } from './returnUrl.js'

// This is the app's open-redirect gate: security.js checks it before handing a value to
// window.location.href. It had no tests, and the guard change in #915 makes it the safety net on the
// most common unauthenticated path rather than a rarely reached manual-URL edge case.
//
// tests/Unit/Http/ReturnUrlTest.php is the other half: the same relative cases run against the backend
// rule, so a policy that drifts on one side fails on the other. That drift is what #917 was.
describe('isSafeReturnUrl', () => {
  it('accepts a same-origin relative path', () => {
    assert.equal(isSafeReturnUrl('/'), true)
    assert.equal(isSafeReturnUrl('/messages'), true)
    assert.equal(isSafeReturnUrl('/band/invitation/abc123'), true)
    assert.equal(isSafeReturnUrl('/band/1/tech-riders?rider=42#top'), true)
  })

  it('refuses an absolute URL', () => {
    assert.equal(isSafeReturnUrl('https://evil.example'), false)
    assert.equal(isSafeReturnUrl('http://evil.example/path'), false)
  })

  it('refuses a protocol relative URL, which would leave the origin', () => {
    assert.equal(isSafeReturnUrl('//evil.example'), false)
  })

  // Browsers normalise a backslash to a slash on a special scheme, so "/\evil.example" resolves
  // exactly like "//evil.example" while still reading as an internal path.
  it('refuses a backslash standing in for the second slash', () => {
    assert.equal(isSafeReturnUrl('/\\evil.example'), false)
  })

  // The URL parser deletes every ASCII tab, CR and LF from the whole input before parsing it, so each
  // of these reaches it as "//evil.example". This is the side that matters most: security.js assigns
  // the value straight to location.href, with no origin in front of it to anchor the authority.
  it('refuses a tab or a newline standing in for the second slash', () => {
    assert.equal(isSafeReturnUrl('/\t/evil.example'), false)
    assert.equal(isSafeReturnUrl('/\n/evil.example'), false)
    assert.equal(isSafeReturnUrl('/\r/evil.example'), false)
    assert.equal(isSafeReturnUrl('/messages\n//evil.example'), false)
  })

  it('refuses a scheme that is not http', () => {
    assert.equal(isSafeReturnUrl('javascript:alert(1)'), false)
    assert.equal(isSafeReturnUrl('data:text/html,<script>'), false)
    assert.equal(isSafeReturnUrl('mailto:someone@example.com'), false)
  })

  it('refuses anything that is not a non-empty string', () => {
    assert.equal(isSafeReturnUrl(''), false)
    assert.equal(isSafeReturnUrl(null), false)
    assert.equal(isSafeReturnUrl(undefined), false)
    assert.equal(isSafeReturnUrl(42), false)
    assert.equal(isSafeReturnUrl(['/messages']), false)
    assert.equal(isSafeReturnUrl({ toString: () => '/messages' }), false)
  })
})
