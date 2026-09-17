import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { IssueBlockedDialog } from './IssueBlockedDialog'

test('the reason stays on screen while the dialog fades out', () => {
  const closeDialog = () => undefined
  const { rerender } = render(
    <IssueBlockedDialog reason="Faktura nemá žádnou položku." onEdit={closeDialog} onClose={closeDialog} />,
  )

  rerender(<IssueBlockedDialog reason={null} onEdit={closeDialog} onClose={closeDialog} />)

  expect(screen.getByText('Faktura nemá žádnou položku.')).toBeInTheDocument()
})
