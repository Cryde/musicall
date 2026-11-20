/**
 * Where the tab reader's static assets live under public/, written by `vite build` (vite.config.js)
 * and served from the site root: alphaTab's music font, and GeneralUser GS split per instrument. The
 * version is in the folder name so a new bank never mixes with files a browser has cached.
 * No imports here: vite.config.js reads this file too.
 */
export const TAB_ASSETS_DIR = 'alphatab'
export const GENERAL_USER_SOURCE = 'assets/soundfont/generaluser-gs-2.0.3/GeneralUser-GS.sf2'
export const GENERAL_USER_DIR = 'soundfont/generaluser-gs-2.0.3'
export const GENERAL_USER_MANIFEST = 'manifest.json'

export const ALPHATAB_FONT_URL = `/${TAB_ASSETS_DIR}/font/`
export const GENERAL_USER_URL = `/${TAB_ASSETS_DIR}/${GENERAL_USER_DIR}/`
