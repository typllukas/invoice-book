/**
 * "1 500,5" with decimals 2 to 150050, null when it is not such a number. String arithmetic, so
 * a typed value never passes through a float.
 */
export function parseScaledInteger(value: string, decimals: number): number | null {
  const groups = new RegExp(`^(?<whole>\\d+)(?:[.,](?<fraction>\\d{1,${String(decimals)}}))?$`, 'u').exec(
    value.replace(/\s/gu, ''),
  )?.groups

  if (groups === undefined || groups.whole === undefined) {
    return null
  }

  return Number(groups.whole) * 10 ** decimals + Number((groups.fraction ?? '').padEnd(decimals, '0'))
}
