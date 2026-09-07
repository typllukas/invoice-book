import { csCZ } from '@mui/material/locale'
import { createTheme } from '@mui/material/styles'
import type { InvoiceStatusTone } from '@/domain/invoice/invoiceStatusTone'

const INK = '#1a1917'
const MUTED = '#6b6862'
const HAIRLINE = '#e7e4dd'
const BRAND = '#24405c'
const SUCCESS = '#2c6549'
const ERROR = '#9b2c2c'
const ROW_HOVER = '#eaeff5'
const CANVAS = '#f7f6f3'
const TABLE_HEAD = '#fbfaf8'

export type InvoiceStampKind = 'issued' | 'paid'

type InvoiceStatusPalette = Record<InvoiceStatusTone, { ink: string; tint: string }>

// module augmentation merges only interfaces
/* eslint-disable @typescript-eslint/consistent-type-definitions */
declare module '@mui/material/styles' {
  interface Palette {
    invoiceStatus: InvoiceStatusPalette
  }

  interface PaletteOptions {
    invoiceStatus: InvoiceStatusPalette
  }
}
/* eslint-enable @typescript-eslint/consistent-type-definitions */

const SOFT_ELEVATION =
  '0 2px 10px -1px rgba(85, 85, 85, .08), 0 1px 10px 0 rgba(85, 85, 85, .06), 0 1px 30px 0 rgba(85, 85, 85, .03)'

export const theme = createTheme({
  palette: {
    primary: { main: BRAND },
    success: { main: SUCCESS },
    warning: { main: '#8a6d1f' },
    error: { main: ERROR },
    background: { default: CANVAS, paper: '#ffffff' },
    text: { primary: INK, secondary: MUTED },
    divider: HAIRLINE,
    action: { hover: ROW_HOVER },
    // chip text is ink on tint; a new color must keep the 4.5:1 contrast of WCAG AA, no test checks it
    invoiceStatus: {
      draft: { ink: '#5c5a54', tint: '#edebe6' },
      unpaid: { ink: '#1d4b74', tint: '#e6eef6' },
      paid: { ink: SUCCESS, tint: '#e3efe8' },
      overdue: { ink: ERROR, tint: '#f6e7e7' },
    },
  },
  shape: { borderRadius: 6 },
  typography: {
    fontFamily: "'Source Sans 3 Variable', system-ui, sans-serif",
    h1: { color: BRAND, fontSize: '1.75rem', fontWeight: 600, letterSpacing: '-0.01em' },
    h2: { fontSize: '1.25rem', fontWeight: 600 },
    body2: { fontVariantNumeric: 'tabular-nums' },
    button: { textTransform: 'none', fontWeight: 600 },
  },
  components: {
    MuiButton: {
      defaultProps: { disableElevation: true, size: 'small' },
      styleOverrides: { root: { whiteSpace: 'nowrap' } },
    },
    MuiLink: { defaultProps: { underline: 'hover' } },
    MuiTextField: { defaultProps: { size: 'small', variant: 'outlined' } },
    MuiPaper: {
      defaultProps: { elevation: 0, variant: 'outlined' },
      styleOverrides: { root: { boxShadow: SOFT_ELEVATION } },
    },
    MuiTableCell: {
      styleOverrides: {
        root: { padding: '11px 14px', borderColor: HAIRLINE },
        sizeSmall: { padding: '6px 14px' },
        head: {
          backgroundColor: TABLE_HEAD,
          color: MUTED,
          fontSize: '0.6875rem',
          fontWeight: 600,
          letterSpacing: '.06em',
          textTransform: 'uppercase',
        },
      },
    },
  },
}, csCZ)
