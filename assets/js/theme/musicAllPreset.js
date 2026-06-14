import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

// Custom preset with steel blue primary color and teal info
const MusicAllPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#f0f5f9',
      100: '#dae6ef',
      200: '#b5cde0',
      300: '#8fb4d0',
      400: '#6b97be',
      500: '#5b87ae',
      600: '#4a7599',
      700: '#3a6589',
      800: '#2d5070',
      900: '#203c57',
      950: '#142838'
    },
    // Light mode uses primary.700 for the functional primary colour so text/links
    // and primary buttons meet WCAG AA (4.5:1) on white AND on the grey content
    // background. Dark mode keeps Aura's default ({primary.400}). The hardcoded
    // logo/hero hex are unaffected.
    colorScheme: {
      light: {
        primary: {
          color: '{primary.700}',
          contrastColor: '#ffffff',
          hoverColor: '{primary.800}',
          activeColor: '{primary.800}'
        }
      }
    }
  },
  components: {
    button: {
      colorScheme: {
        light: {
          root: {
            info: {
              background: '{teal.700}',
              hoverBackground: '{teal.800}',
              activeBackground: '{teal.900}',
              borderColor: '{teal.700}',
              hoverBorderColor: '{teal.800}',
              activeBorderColor: '{teal.900}',
              color: '#ffffff',
              hoverColor: '#ffffff',
              activeColor: '#ffffff',
              focusRing: {
                color: '{teal.700}',
                shadow: 'none'
              }
            }
          },
          outlined: {
            info: {
              hoverBackground: '{teal.50}',
              activeBackground: '{teal.100}',
              borderColor: '{teal.600}',
              color: '{teal.700}'
            }
          },
          text: {
            info: {
              hoverBackground: '{teal.50}',
              activeBackground: '{teal.100}',
              color: '{teal.700}'
            }
          }
        },
        dark: {
          root: {
            info: {
              background: '{teal.400}',
              hoverBackground: '{teal.300}',
              activeBackground: '{teal.200}',
              borderColor: '{teal.400}',
              hoverBorderColor: '{teal.300}',
              activeBorderColor: '{teal.200}',
              color: '{teal.950}',
              hoverColor: '{teal.950}',
              activeColor: '{teal.950}',
              focusRing: {
                color: '{teal.400}',
                shadow: 'none'
              }
            }
          },
          outlined: {
            info: {
              hoverBackground: 'color-mix(in srgb, {teal.400}, transparent 96%)',
              activeBackground: 'color-mix(in srgb, {teal.400}, transparent 84%)',
              borderColor: '{teal.700}',
              color: '{teal.400}'
            }
          },
          text: {
            info: {
              hoverBackground: 'color-mix(in srgb, {teal.400}, transparent 96%)',
              activeBackground: 'color-mix(in srgb, {teal.400}, transparent 84%)',
              color: '{teal.400}'
            }
          }
        }
      }
    }
  }
})

export default MusicAllPreset
