import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { ConfirmDialog } from './ConfirmDialog'

test('the dialog is announced with its message, not only its title', () => {
  const closeDialog = () => undefined
  render(
    <ConfirmDialog
      open
      title="Smazat koncept?"
      message="Koncept faktury se smaže i s položkami."
      confirmLabel="Smazat"
      onConfirm={closeDialog}
      onCancel={closeDialog}
    />,
  )

  expect(screen.getByRole('dialog', { name: 'Smazat koncept?' })).toHaveAccessibleDescription(
    'Koncept faktury se smaže i s položkami.',
  )
})
