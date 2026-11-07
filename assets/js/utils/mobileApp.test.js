import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { mobileAppStores, offersMobileApp } from './mobileApp.js'

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
const IPAD = {
  userAgent: 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
  maxTouchPoints: 5
}
const IPADOS = {
  userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15',
  maxTouchPoints: 5
}

describe('mobileAppStores', () => {
  it('points an iPhone and an iPad at the App Store', () => {
    assert.deepEqual(mobileAppStores(IPHONE), ['ios'])
    assert.deepEqual(mobileAppStores(IPAD), ['ios'])
  })

  it('tells an iPad asking for the desktop site from a Mac by its touch points', () => {
    assert.deepEqual(mobileAppStores(IPADOS), ['ios'])
  })

  it('points an Android phone at the Play Store', () => {
    assert.deepEqual(mobileAppStores(ANDROID), ['android'])
  })

  it('shows both stores on a computer, a Mac included', () => {
    assert.deepEqual(mobileAppStores(DESKTOP), ['android', 'ios'])
    assert.deepEqual(mobileAppStores(MAC), ['android', 'ios'])
  })

  it('shows both when the browser says nothing about itself', () => {
    assert.deepEqual(mobileAppStores(), ['android', 'ios'])
  })
})

describe('offersMobileApp', () => {
  it('offers the app to a visitor or a member who does not have it', () => {
    assert.equal(offersMobileApp(null), true)
    assert.equal(offersMobileApp({ has_mobile_app: false }), true)
  })

  it('stops once the member is signed in on the app', () => {
    assert.equal(offersMobileApp({ has_mobile_app: true }), false)
  })
})
