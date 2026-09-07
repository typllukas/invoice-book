import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'

export function AppErrorFallback() {
  return (
    <>
      <Alert severity="error" sx={{ marginBlockEnd: 2 }}>
        <AlertTitle>Aplikace narazila na neočekávanou chybu.</AlertTitle>
        Zkuste stránku načíst znovu. Podrobnosti chyby jsou v konzoli prohlížeče.
      </Alert>
      <Stack direction="row" spacing={2}>
        <Button
          variant="contained"
          onClick={() => {
            window.location.reload()
          }}
        >
          Načíst znovu
        </Button>
        {/* a plain link, the router may be what broke */}
        <Button href="/">Zpět na seznam</Button>
      </Stack>
    </>
  )
}
