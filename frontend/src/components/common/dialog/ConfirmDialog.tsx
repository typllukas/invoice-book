import Button, { type ButtonProps } from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import { useId, type ReactNode } from 'react'

export type FormSubmitButtonProps = Pick<ButtonProps, 'type' | 'form' | 'name' | 'value'>

type ConfirmDialogProps = {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  cancelLabel?: string
  confirmColor?: 'primary' | 'error'
  confirmIcon?: ReactNode
  isConfirming?: boolean
  confirmButtonProps?: FormSubmitButtonProps
  onConfirm: () => void
  onCancel: () => void
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel,
  cancelLabel = 'Zrušit',
  confirmColor = 'primary',
  confirmIcon,
  isConfirming = false,
  confirmButtonProps,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const messageId = useId()

  return (
    <Dialog open={open} onClose={isConfirming ? undefined : onCancel} aria-describedby={messageId}>
      <DialogTitle>{title}</DialogTitle>
      <DialogContent>
        <DialogContentText id={messageId}>{message}</DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button variant="outlined" onClick={onCancel} disabled={isConfirming}>
          {cancelLabel}
        </Button>
        <Button
          {...confirmButtonProps}
          color={confirmColor}
          startIcon={confirmIcon}
          variant="contained"
          loading={isConfirming}
          onClick={onConfirm}
        >
          {confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
