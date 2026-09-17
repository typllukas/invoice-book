import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import { useState } from 'react'
import { ConfirmDialog } from '@/components/common/dialog/ConfirmDialog'

type IssueBlockedDialogProps = {
  reason: string | null
  onEdit: () => void
  onClose: () => void
}

export function IssueBlockedDialog({ reason, onEdit, onClose }: IssueBlockedDialogProps) {
  const [shownReason, setShownReason] = useState(reason)
  if (reason !== null && reason !== shownReason) {
    setShownReason(reason)
  }

  return (
    <ConfirmDialog
      open={reason !== null}
      title="Fakturu zatím nelze vystavit"
      message={shownReason ?? ''}
      confirmLabel="Upravit koncept"
      confirmIcon={<EditOutlinedIcon />}
      cancelLabel="Zavřít"
      onConfirm={onEdit}
      onCancel={onClose}
    />
  )
}
