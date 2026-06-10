import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, router } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'

type ArticleCard = {
  slug: string
  title: string
  summary: string | null
  view_count: number
  published_at: string | null
  categories: { name: string; slug: string }[]
}

type Category = {
  name: string
  slug: string
  description: string | null
  articles_count: number
  children: { name: string; slug: string; articles_count: number }[]
}

type Props = {
  featured: ArticleCard[]
  recent: ArticleCard[]
  categories: Category[]
}

type Suggestion = { title: string; slug: string; summary: string | null }

/** Search box with AJAX autosuggest against /admin/kb/suggest. */
const SearchBox = () => {
  const [q, setQ] = useState('')
  const [suggestions, setSuggestions] = useState<Suggestion[]>([])
  const [open, setOpen] = useState(false)
  const debounce = useRef<ReturnType<typeof setTimeout>>(undefined)

  useEffect(() => {
    clearTimeout(debounce.current)
    if (q.trim().length < 2) {
      setSuggestions([])
      setOpen(false)
      return
    }
    debounce.current = setTimeout(async () => {
      try {
        const res = await fetch(`/admin/kb/suggest?q=${encodeURIComponent(q.trim())}`, { headers: { Accept: 'application/json' } })
        const json = (await res.json()) as { results: Suggestion[] }
        setSuggestions(json.results)
        setOpen(true)
      } catch {
        setSuggestions([])
      }
    }, 250)
    return () => clearTimeout(debounce.current)
  }, [q])

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (q.trim().length >= 2) {
      router.get('/admin/kb/search', { q: q.trim() })
    }
  }

  return (
    <form onSubmit={submit} className="relative mx-auto w-full max-w-xl">
      <div className="input-icon-group">
        <Icon icon="search" className="input-icon" />
        <input
          className="form-input w-full py-2.5"
          placeholder="Search the knowledge base..."
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onFocus={() => suggestions.length > 0 && setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
        />
      </div>
      {open && suggestions.length > 0 && (
        <div className="border-default-200 bg-card absolute z-20 mt-1 w-full rounded-md border shadow-lg">
          {suggestions.map((s) => (
            <Link key={s.slug} href={`/admin/kb/article/${s.slug}`} className="hover:bg-light block px-4 py-2.5">
              <p className="font-medium">{s.title}</p>
              {s.summary && <p className="text-default-400 truncate text-xs">{s.summary}</p>}
            </Link>
          ))}
        </div>
      )}
    </form>
  )
}

const ArticleListCard = ({ title, articles }: { title: string; articles: ArticleCard[] }) => (
  <div className="card">
    <div className="card-header">
      <h4 className="card-title">{title}</h4>
    </div>
    <div className="card-body">
      <ul className="divide-default-200 divide-y">
        {articles.map((a) => (
          <li key={a.slug} className="py-2.5 first:pt-0 last:pb-0">
            <Link href={`/admin/kb/article/${a.slug}`} className="hover:text-primary font-semibold">
              {a.title}
            </Link>
            {a.summary && <p className="text-default-400 truncate text-sm">{a.summary}</p>}
            <p className="text-default-400 mt-0.5 text-xs">
              {a.categories.map((c) => c.name).join(', ')}
              {a.published_at && ` · ${a.published_at}`}
            </p>
          </li>
        ))}
      </ul>
    </div>
  </div>
)

const Page = ({ featured, recent, categories }: Props) => (
  <>
    <Head title="Knowledge Base" />
    <PageBreadcrumb title="Knowledge Base" subtitle="Browse" />

    <div className="card mb-6">
      <div className="card-body py-8">
        <h4 className="mb-1 text-center text-lg font-bold">How can we help?</h4>
        <p className="text-default-400 mb-4 text-center">Guides, procedures, and answers for day-to-day work.</p>
        <SearchBox />
      </div>
    </div>

    {categories.length > 0 && (
      <div className="gap-base mb-6 grid sm:grid-cols-2 xl:grid-cols-3">
        {categories.map((c) => (
          <div key={c.slug} className="card">
            <div className="card-body">
              <Link href={`/admin/kb/category/${c.slug}`} className="hover:text-primary flex items-center gap-2 font-semibold">
                <span className="btn btn-icon bg-primary/10 size-8!">
                  <Icon icon="folder" className="text-primary text-lg" />
                </span>
                {c.name}
                <span className="text-default-400 text-xs font-normal">({c.articles_count})</span>
              </Link>
              {c.description && <p className="text-default-400 mt-1.5 text-sm">{c.description}</p>}
              {c.children.length > 0 && (
                <ul className="mt-2 space-y-1">
                  {c.children.map((child) => (
                    <li key={child.slug}>
                      <Link href={`/admin/kb/category/${child.slug}`} className="text-default-500 hover:text-primary text-sm">
                        › {child.name} <span className="text-default-400 text-xs">({child.articles_count})</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        ))}
      </div>
    )}

    <div className="gap-base grid lg:grid-cols-2">
      {featured.length > 0 && <ArticleListCard title="Featured" articles={featured} />}
      {recent.length > 0 && <ArticleListCard title="Recently published" articles={recent} />}
    </div>

    {categories.length === 0 && featured.length === 0 && recent.length === 0 && (
      <div className="card">
        <div className="card-body py-10 text-center">
          <p className="text-default-400">Nothing published yet — check back soon.</p>
        </div>
      </div>
    )}
  </>
)

export default Page
