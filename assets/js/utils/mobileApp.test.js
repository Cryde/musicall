import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { advertisesMobileApp, offersMobileApp } from './mobileApp.js'

const ANDROID = {
  userAgent: 'Mozilla/5.0 (Linux; Android 15; Pixel 8) Chrome/130.0 Mobile Safari/537.36',
  maxTouchPoints: 5
}
const DESKTOP = {
  userAgent: 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0 Safari/537.36',
  maxTouchPoints: 0
}
const MAC = {
  userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15',
  maxTouchPoints: 0
}
const IPHONE = {
  userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148',
  maxTouchPoints: 5
}
const IPADOS = {
  userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15',
  maxTouchPoints: 5
}

describe('advertisesMobileApp', () => {
  it('points Android phones and computers at the Play Store', () => {
    assert.equal(advertisesMobileApp(ANDROID), true)
    assert.equal(advertisesMobileApp(DESKTOP), true)
    assert.equal(advertisesMobileApp(MAC), true)
  })

  it('never points an iPhone or an iPad at it, since there is no iOS build', () => {
    assert.equal(advertisesMobileApp(IPHONE), false)
    assert.equal(
      advertisesMobileApp({
        userAgent: 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
        maxTouchPoints: 5
      }),
      false
    )
  })

  it('tells an iPad asking for the desktop site from a Mac by its touch points', () => {
    assert.equal(advertisesMobileApp(IPADOS), false)
  })

  it('advertises when the browser says nothing about itself', () => {
    assert.equal(advertisesMobileApp(), true)
  })
})

describe('offersMobileApp', () => {
  it('offers the app to a visitor or a member who does not have it', () => {
    assert.equal(offersMobileApp(null, DESKTOP), true)
    assert.equal(offersMobileApp({ has_mobile_app: false }, DESKTOP), true)
  })

  it('stops once the member is signed in on the app', () => {
    assert.equal(offersMobileApp({ has_mobile_app: true }, ANDROID), false)
  })

  it('still hides it on an iPhone', () => {
    assert.equal(offersMobileApp({ has_mobile_app: false }, IPHONE), false)
  })
})
