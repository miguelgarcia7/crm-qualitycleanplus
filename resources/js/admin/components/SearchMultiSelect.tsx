import Icon from '@/components/wrappers/Icon'
import { useMemo, useRef, useState } from 'react'

type Option = { id: number; name: string }

/**
 * React-controlled searchable multi-select (ADR-0027: form-bound widgets are
 * React, not Preline). Selected options render as removable chips; the input
 * filters the option list. Built for long lists (properties, people) where a
 * checkbox wall doesn't scale.
 */
const SearchMultiSelect = ({
  options,
  selected,
  onChange,
  placeholder = 'Type to search…',
  emptyText = 'No matches.',
}: {
  options: Option[]
  selected: number[]
  onChange: (ids: number[]) => void
  placeholder?: string
  emptyText?: string
}) => {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const blurTimer = useRef<ReturnType<typeof setTimeout>>(null)

  const selectedOptions = useMemo(() => options.filter((o) => selected.includes(o.id)), [options, selected])
  const matches = useMemo(
    () =>
      options.filter(
        (o) => !selected.includes(o.id) && (query.trim() === '' || o.name.toLowerCase().includes(query.trim().toLowerCase())),
      ),
    [options, selected, query],
  )

  const add = (id: number) => {
    onChange([...selected, id])
    setQuery('')
  }

  const remove = (id: number) => onChange(selected.filter((s) => s !== id))

  // Delay closing so a click on a list item lands before the list unmounts.
  const onBlur = () => {
    blurTimer.current = setTimeout(() => setOpen(false), 150)
  }
  const onFocus = () => {
    if (blurTimer.current) clearTimeout(blurTimer.current)
    setOpen(true)
  }

  return (
    <div className="relative">
      {selectedOptions.length > 0 && (
        <div className="mb-2 flex flex-wrap gap-1.5">
          {selectedOptions.map((o) => (
            <span key={o.id} className="badge badge-label bg-primary/15 text-primary inline-flex items-center gap-1.5 py-1">
              {o.name}
              <button type="button" onClick={() => remove(o.id)} title={`Remove ${o.name}`}>
                <Icon icon="x" className="size-3.5" />
              </button>
            </span>
          ))}
        </div>
      )}

      <div className="input-icon-group">
        <Icon icon="search" className="input-icon" />
        <input
          className="form-input w-full"
          value={query}
          placeholder={placeholder}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={onFocus}
          onBlur={onBlur}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              if (matches.length > 0) add(matches[0].id)
            }
            if (e.key === 'Escape') setOpen(false)
          }}
        />
      </div>

      {open && (
        <div className="border-default-200 bg-card absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border shadow-lg">
          {matches.length > 0 ? (
            matches.map((o) => (
              <button
                key={o.id}
                type="button"
                className="hover:bg-light/60 block w-full px-3 py-2 text-start text-sm"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => add(o.id)}
              >
                {o.name}
              </button>
            ))
          ) : (
            <p className="text-default-400 px-3 py-2 text-sm">{emptyText}</p>
          )}
        </div>
      )}
    </div>
  )
}

export default SearchMultiSelect
