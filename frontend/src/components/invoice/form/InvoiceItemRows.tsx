import AddRoundedIcon from '@mui/icons-material/AddRounded'
import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutlined'
import Button from '@mui/material/Button'
import IconButton from '@mui/material/IconButton'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Tooltip from '@mui/material/Tooltip'
import Typography from '@mui/material/Typography'
import type { InvoiceInput } from '@/api/schema/types'
import { formatMinorUnits } from '@/domain/pricing/amount'
import { calculateLineNetAmount, VAT_RATE_LABELS, VAT_RATES, type VatRateValue } from '@/domain/pricing/vat'
import {
  createEmptyInvoiceItemDraft,
  readQuantityInThousandths,
  readUnitPriceNet,
  type InvoiceItemDraft,
} from '@/domain/invoice/form/invoiceFormValues'

function readVatRate(value: string): VatRateValue {
  const vatRate = VAT_RATES.find((knownVatRate) => knownVatRate === value)
  if (vatRate === undefined) {
    throw new Error(`The VAT rate menu offered ${value}, which is not a VAT rate.`)
  }

  return vatRate
}

type InvoiceItemField = keyof NonNullable<InvoiceInput['items']>[number]

type InvoiceItemFieldName = `items.${number}.${InvoiceItemField}`

type InvoiceItemRowsProps = {
  items: readonly InvoiceItemDraft[]
  onItemsChange: (items: InvoiceItemDraft[]) => void
  readFieldError: (fieldName: InvoiceItemFieldName) => string | undefined
}

export function InvoiceItemRows({ items, onItemsChange, readFieldError }: InvoiceItemRowsProps) {
  const changeItem = (index: number, patch: Partial<InvoiceItemDraft>) => {
    onItemsChange(
      items.map((item, itemIndex) => (itemIndex === index ? { ...item, ...patch } : item)),
    )
  }

  return (
    <Stack>
      {items.map((item, index) => {
        const bindItemError = (field: InvoiceItemField) => ({
          error: readFieldError(`items.${index}.${field}`) !== undefined,
          helperText: readFieldError(`items.${index}.${field}`) ?? ' ',
        })

        return (
          <Stack key={item.key} direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
            <TextField
              label="Popis"
              value={item.description}
              onChange={(event) => {
                changeItem(index, { description: event.target.value })
              }}
              name={`items.${index}.description`}
              {...bindItemError('description')}
              sx={{ flexGrow: 1 }}
            />
            <TextField
              label="Množství"
              value={item.quantity}
              onChange={(event) => {
                changeItem(index, { quantity: event.target.value })
              }}
              name={`items.${index}.quantity`}
              {...bindItemError('quantity')}
              sx={{ width: 110 }}
            />
            <TextField
              label="MJ"
              value={item.unit}
              onChange={(event) => {
                changeItem(index, { unit: event.target.value })
              }}
              name={`items.${index}.unit`}
              {...bindItemError('unit')}
              sx={{ width: 80 }}
            />
            <TextField
              label="Cena za MJ bez DPH"
              value={item.unitPrice}
              onChange={(event) => {
                changeItem(index, { unitPrice: event.target.value })
              }}
              name={`items.${index}.unitPriceNet`}
              {...bindItemError('unitPriceNet')}
              sx={{ width: 170 }}
            />
            <TextField
              select
              label="Sazba DPH"
              value={item.vatRate}
              onChange={(event) => {
                changeItem(index, { vatRate: readVatRate(event.target.value) })
              }}
              name={`items.${index}.vatRate`}
              {...bindItemError('vatRate')}
              sx={{ width: 120 }}
            >
              {VAT_RATES.map((vatRate) => (
                <MenuItem key={vatRate} value={vatRate}>
                  {VAT_RATE_LABELS[vatRate]}
                </MenuItem>
              ))}
            </TextField>
            <Typography
              component="output"
              aria-label={`Celkem bez DPH, položka ${index + 1}`}
              variant="body2"
              sx={{ width: 120, paddingBlockStart: 1 }}
              align="right"
            >
              {formatMinorUnits(
                calculateLineNetAmount({
                  quantityInThousandths: readQuantityInThousandths(item),
                  unitPriceNet: readUnitPriceNet(item),
                  vatRate: item.vatRate,
                }),
              )}
            </Typography>
            <Tooltip title="Smazat">
              <IconButton
                aria-label={`Smazat položku ${index + 1}`}
                color="error"
                size="small"
                onClick={() => {
                  onItemsChange(items.filter((_item, itemIndex) => itemIndex !== index))
                }}
              >
                <DeleteOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
        )
      })}

      <Button
        onClick={() => {
          onItemsChange([...items, createEmptyInvoiceItemDraft()])
        }}
        startIcon={<AddRoundedIcon />}
        sx={{ alignSelf: 'flex-start' }}
      >
        Přidat položku
      </Button>
    </Stack>
  )
}
