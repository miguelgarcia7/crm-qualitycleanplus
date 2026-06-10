import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

type ArticleCard = {
  slug: string
  title: string
  summary: string | null
  is_featured: boolean
  categories: string[]
}

type Props = {
  q: string
  articles: ArticleCard[]
}

const Page = ({ q, articles }: Props) => {
  const [term, setTerm] = useState(q)

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    router.get('/kb', term.trim().length >= 2 ? { q: term.trim() } : {}, { preserveState: true })
  }

  return (
    <>
      <Head title="Knowledge Base" />
      <PageBreadcrumb title="Knowledge Base" subtitle="QC Minute" />

      <form onSubmit={submit} className="card mb-4">
        <div className="card-body flex gap-2 p-4">
          <div className="input-icon-group grow">
            <Icon icon="search" className="input-icon" />
            <input className="form-input w-full" placeholder="Search articles..." value={term} onChange={(e) => setTerm(e.target.value)} />
          </div>
          <button className="btn bg-primary hover:bg-primary-hover text-white">Search</button>
        </div>
      </form>

      {q && (
        <p className="text-default-400 mb-3 text-sm">
          {articles.length} result{articles.length === 1 ? '' : 's'} for “{q}” —{' '}
          <Link href="/kb" className="text-primary">
            clear
          </Link>
        </p>
      )}

      <div className="grid gap-4">
        {articles.length === 0 ? (
          <div className="card">
            <div className="card-body py-10 text-center">
              <p className="text-default-400">{q ? 'No articles match your search.' : 'Nothing published yet — check back soon.'}</p>
            </div>
          </div>
        ) : (
          articles.map((a) => (
            <Link key={a.slug} href={`/kb/${a.slug}`} className="card rounded-2xl transition hover:shadow-lg">
              <div className="card-body p-5">
                <h5 className="font-semibold">
                  {a.is_featured && <Icon icon="star-filled" className="text-warning me-1 inline size-3.5" />}
                  {a.title}
                </h5>
                {a.summary && <p className="text-default-400 mt-1 text-sm">{a.summary}</p>}
                {a.categories.length > 0 && <p className="text-default-400 mt-1.5 text-xs">{a.categories.join(', ')}</p>}
              </div>
            </Link>
          ))
        )}
      </div>
    </>
  )
}

export default Page
