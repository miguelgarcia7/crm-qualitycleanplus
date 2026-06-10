import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type ArticleCard = {
  slug: string
  title: string
  summary: string | null
  view_count: number
  published_at: string | null
  categories: { name: string; slug: string }[]
}

type Props = {
  heading: string
  context: 'search' | 'category' | 'tag'
  description?: string | null
  q?: string
  articles: ArticleCard[]
}

const Page = ({ heading, context, description, articles }: Props) => (
  <>
    <Head title={heading} />
    <PageBreadcrumb title={heading} subtitle="Knowledge Base" />

    <div className="mb-4 flex flex-wrap items-center gap-3">
      <Link href="/admin/kb" className="btn btn-sm btn-light">
        ← Knowledge Base
      </Link>
      <span className="text-default-400 text-sm">
        {articles.length} article{articles.length === 1 ? '' : 's'}
      </span>
    </div>

    {description && <p className="text-default-500 mb-4">{description}</p>}

    <div className="card">
      <div className="card-body">
        {articles.length === 0 ? (
          <p className="text-default-400 py-6 text-center">
            {context === 'search' ? 'No articles match your search.' : 'No published articles here yet.'}
          </p>
        ) : (
          <ul className="divide-default-200 divide-y">
            {articles.map((a) => (
              <li key={a.slug} className="py-3 first:pt-0 last:pb-0">
                <Link href={`/admin/kb/article/${a.slug}`} className="hover:text-primary font-semibold">
                  {a.title}
                </Link>
                {a.summary && <p className="text-default-400 text-sm">{a.summary}</p>}
                <p className="text-default-400 mt-0.5 text-xs">
                  {a.categories.map((c) => c.name).join(', ')}
                  {a.published_at && ` · ${a.published_at}`} · {a.view_count} views
                </p>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  </>
)

export default Page
