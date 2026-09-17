import { useEffect, useState } from 'react'

export function useDebouncedValue<Value>(value: Value, delayMilliseconds: number) {
  const [debouncedValue, setDebouncedValue] = useState(value)

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      setDebouncedValue(value)
    }, delayMilliseconds)

    return () => {
      clearTimeout(timeoutId)
    }
  }, [value, delayMilliseconds])

  return debouncedValue
}
