import Breadcrumbs from '@mui/material/Breadcrumbs'
import Link from '@mui/material/Link'
import Typography from '@mui/material/Typography'
import { Link as RouterLink } from 'react-router'

/**
 * The trail is the page title, so its last item is the h1 and the page keeps a single heading.
 */
export function InvoiceBreadcrumbs({ heading }: { heading: string }) {
  return (
    <Breadcrumbs
      aria-label="Drobečková navigace"
      sx={(theme) => ({ ...theme.typography.h1, color: 'text.secondary', marginBlockEnd: 3 })}
    >
      <Link component={RouterLink} to="/" color="inherit">
        Seznam faktur
      </Link>
      <Typography variant="h1" aria-current="page">
        {heading}
      </Typography>
    </Breadcrumbs>
  )
}
