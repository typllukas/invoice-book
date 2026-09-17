import LabelImportantRoundedIcon from '@mui/icons-material/LabelImportantRounded'
import { ConfirmDialog, type FormSubmitButtonProps } from '@/components/common/dialog/ConfirmDialog'

type IssueConfirmationDialogProps = {
  open: boolean
  onConfirm: () => void
  onCancel: () => void
} & (
  | { isConfirming: boolean; confirmButtonProps?: never }
  | { confirmButtonProps: FormSubmitButtonProps; isConfirming?: never }
)

export function IssueConfirmationDialog(props: IssueConfirmationDialogProps) {
  return (
    <ConfirmDialog
      {...props}
      title="Vystavit fakturu?"
      message="Faktura dostane číslo a potom už ji nepůjde upravit."
      confirmLabel="Vystavit"
      confirmIcon={<LabelImportantRoundedIcon />}
    />
  )
}
