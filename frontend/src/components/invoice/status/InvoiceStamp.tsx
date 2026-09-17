import Box from '@mui/material/Box'
import { motion, type TargetAndTransition } from 'motion/react'
import type { InvoiceStampKind } from '@/config/theme'
import type { InvoiceStatusTone } from '@/domain/invoice/invoiceStatusTone'
import { INVOICE_STAMP_LABELS } from './invoiceStatusLabels'

const STAMP_TILT: Readonly<Record<InvoiceStampKind, number>> = { issued: -9, paid: 6 }

// the issued stamp turns into the unpaid chip, so it takes its color
const STAMP_TONE: Readonly<Record<InvoiceStampKind, InvoiceStatusTone>> = { issued: 'unpaid', paid: 'paid' }

// fractal noise as a mask, so the ink comes out uneven like from a rubber stamp
const INK_TEXTURE =
  "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='80'%3E%3Cfilter id='ink'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='2' seed='7'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 -3.4 0 0 0 2.75'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23ink)'/%3E%3C/svg%3E\")"

// held above the paper, slightly out of focus
const PRESS_START = { scale: 1.2, filter: 'blur(1.5px)' } satisfies TargetAndTransition

// the ink bleeds a little into the paper once pressed
const SETTLED_INK = { opacity: 0.9, filter: 'blur(0.35px)' } satisfies TargetAndTransition

// lets the eye settle on the document the page just switched to before the stamp lands
const DOCUMENT_STAMP_DELAY = 0.1

// on the document, or landing in a list row before it turns into the status chip
type InvoiceStampProps = { kind: InvoiceStampKind } & (
  | { caption: string | null; animateOnMount: boolean; layoutId?: never; onLanded?: never }
  | { layoutId: string; onLanded: () => void; caption?: never; animateOnMount?: never }
)

/**
 * The hold at the end of the keyframes keeps the stamp readable before onLanded lets a caller replace it.
 */
export function InvoiceStamp({
  kind,
  caption = null,
  animateOnMount = true,
  layoutId,
  onLanded,
}: InvoiceStampProps) {
  const label = INVOICE_STAMP_LABELS[kind]
  const tilt = STAMP_TILT[kind]

  return (
    <motion.div
      role="img"
      aria-label={caption === null ? `Razítko ${label}` : `Razítko ${label} ${caption}`}
      layoutId={layoutId}
      initial={animateOnMount ? { opacity: 0, ...PRESS_START, rotate: tilt } : false}
      animate={
        animateOnMount
          ? {
              opacity: [0, 1, 1, 1, SETTLED_INK.opacity],
              scale: [PRESS_START.scale, 0.97, 1.015, 1, 1],
              filter: [PRESS_START.filter, 'blur(0px)', 'blur(0px)', SETTLED_INK.filter, SETTLED_INK.filter],
              rotate: tilt,
            }
          : { ...SETTLED_INK, scale: 1, rotate: tilt }
      }
      transition={{
        duration: animateOnMount ? 0.7 : 0,
        delay: animateOnMount && layoutId === undefined ? DOCUMENT_STAMP_DELAY : 0,
        times: [0, 0.16, 0.24, 0.34, 1],
        ease: ['easeIn', 'easeOut', 'easeOut', 'linear'],
      }}
      onAnimationComplete={onLanded}
      style={{ display: 'inline-block', pointerEvents: 'none' }}
    >
      <Box
        sx={{
          border: '3px solid currentColor',
          borderRadius: '6px',
          color: `invoiceStatus.${STAMP_TONE[kind]}.ink`,
          fontWeight: 800,
          letterSpacing: '.12em',
          lineHeight: 1.2,
          maskImage: INK_TEXTURE,
          mixBlendMode: 'multiply',
          paddingBlock: '3px',
          paddingInline: '12px',
          textAlign: 'center',
          textTransform: 'uppercase',
          whiteSpace: 'nowrap',
        }}
      >
        {label}
        {caption !== null && (
          <Box component="span" sx={{ display: 'block', fontSize: '0.7rem', letterSpacing: '.08em' }}>
            {caption}
          </Box>
        )}
      </Box>
    </motion.div>
  )
}
