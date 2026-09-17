import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutlined'
import { ConfirmDialog } from '@/components/common/dialog/ConfirmDialog'
import type { InvoiceListActions } from '@/hooks/invoice/useInvoiceListActions'
import { IssueBlockedDialog } from '@/components/invoice/dialog/IssueBlockedDialog'
import { IssueConfirmationDialog } from '@/components/invoice/dialog/IssueConfirmationDialog'

export function InvoiceActionDialogs({ actions }: { actions: InvoiceListActions }) {
  const isIssueRequested = actions.pendingAction?.kind === 'issue'

  return (
    <>
      <IssueBlockedDialog
        reason={actions.issueBlocker}
        onEdit={actions.editBlockedDraft}
        onClose={actions.closePendingAction}
      />

      <IssueConfirmationDialog
        open={isIssueRequested && actions.issueBlocker === null}
        isConfirming={actions.isIssuing}
        onConfirm={actions.confirmIssue}
        onCancel={actions.closePendingAction}
      />

      <ConfirmDialog
        open={actions.pendingAction?.kind === 'delete'}
        title="Smazat koncept?"
        message="Koncept faktury se smaže i s položkami. Tuto akci nelze vrátit."
        confirmLabel="Smazat"
        confirmIcon={<DeleteOutlinedIcon />}
        confirmColor="error"
        isConfirming={actions.isDeleting}
        onConfirm={actions.confirmDelete}
        onCancel={actions.closePendingAction}
      />
    </>
  )
}
