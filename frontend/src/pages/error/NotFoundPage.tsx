import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import { Link as RouterLink } from 'react-router'

export function NotFoundPage() {
  return (
    <>
      <Typography variant="h1" sx={{ marginBlockEnd: 2 }}>
        Stránka nenalezena
      </Typography>
      <Typography sx={{ marginBlockEnd: 3 }} color="text.secondary">
        Tato adresa v knize faktur není.
      </Typography>
      <Button variant="contained" component={RouterLink} to="/">
        Zpět na seznam
      </Button>
    </>
  )
}
