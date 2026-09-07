import ImportContactsRoundedIcon from '@mui/icons-material/ImportContactsRounded'
import AppBar from '@mui/material/AppBar'
import Container from '@mui/material/Container'
import Stack from '@mui/material/Stack'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import type { ReactNode } from 'react'
import { Link as RouterLink } from 'react-router'
import { TechStackLogos } from './TechStackLogos'

export function AppShell({ children }: { children: ReactNode }) {
  return (
    <>
      <AppBar position="static" color="primary">
        <Toolbar variant="dense">
          <Stack
            direction="row"
            component={RouterLink}
            to="/"
            sx={{ alignItems: 'center', gap: 1, marginInlineEnd: 'auto', color: 'inherit', textDecoration: 'none' }}
          >
            <ImportContactsRoundedIcon fontSize="small" />
            <Typography variant="h2" component="span" color="inherit">
              Kniha faktur
            </Typography>
          </Stack>
          <TechStackLogos />
        </Toolbar>
      </AppBar>
      <Container maxWidth="lg" sx={{ paddingBlock: 3 }}>
        {children}
      </Container>
    </>
  )
}
