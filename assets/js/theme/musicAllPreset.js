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
    }
  },
  components: {
    button: {
      colorScheme: {
        light: {
          root: {
            info: {
              background: '{teal.500}',
              hoverBackground: '{teal.600}',
              activeBackground: '{teal.700}',
              borderColor: '{teal.500}',
              hoverBorderColor: '{teal.600}',
              activeBorderColor: '{teal.700}',
              color: '#ffffff',
              hoverColor: '#ffffff',
              activeColor: '#ffffff',
              focusRing: {
                color: '{teal.500}',
                shadow: 'none'
              }
            }
          },
          outlined: {
            info: {
              hoverBackground: '{teal.50}',
              activeBackground: '{teal.100}',
              borderColor: '{teal.200}',
              color: '{teal.500}'
            }
          },
          text: {
            info: {
              hoverBackground: '{teal.50}',
              activeBackground: '{teal.100}',
              color: '{teal.500}'
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
