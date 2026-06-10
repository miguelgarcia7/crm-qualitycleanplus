import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import FeedbackWidget from '@/views/admin/kb/components/FeedbackWidget'
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
  article: {
    slug: string
    title: string
    summary: string | null
    content: string
    author: string
    published_at: string | null
    view_count: number
    categories: { name: string; slug: string }[]
    tags: { name: string; slug: string }[]
  }
  attachments: { id: number; name: string; size: number; is_image: boolean }[]
  related: ArticleCard[]
  my_vote: string | null
  can_edit: boolean
}

const formatSize = (bytes: number) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`)

const Page = ({ article, attachments, related, my_vote, can_edit }: Props) => {
  const images = attachments.filter((a) => a.is_image)
  const documents = attachments.filter((a) => !a.is_image)

  return (
    <>
      <Head title={article.title} />
      <PageBreadcrumb title={article.title} subtitle="Knowledge Base" />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Link href="/admin/kb" className="btn btn-sm btn-light">
          ← Knowledge Base
        </Link>
        <span className="grow" />
        {can_edit && (
          <Link href={`/admin/kb/articles/${article.slug}`} className="btn btn-sm bg-primary/15 text-primary hover:bg-primary hover:text-white">
            <Icon icon="edit" className="me-1 size-4" /> Manage
          </Link>
        )}
      </div>

      <div className="gap-base grid xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <div className="card">
            <div className="card-header block">
              <h4 className="card-title mb-1.25">{article.title}</h4>
              <p className="text-default-400 text-sm">
                By {article.author}
                {article.published_at && ` · ${article.published_at}`} · {article.view_count} views
              </p>
            </div>
            <div className="card-body">
              {article.summary && <p className="text-default-500 border-default-200 mb-4 border-b pb-4 italic">{article.summary}</p>}
              <div className="ql-snow">
                <div className="ql-editor !p-0" dangerouslySetInnerHTML={{ __html: article.content }} />
              </div>

              {images.length > 0 && (
                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                  {images.map((img) => (
                    <a key={img.id} href={`/admin/kb/attachments/${img.id}`} title={img.name}>
                      <img src={`/admin/kb/attachments/${img.id}`} alt={img.name} className="border-default-200 w-full rounded-md border" />
                    </a>
                  ))}
                </div>
              )}
            </div>
          </div>

          <FeedbackWidget endpoint={`/admin/kb/article/${article.slug}/feedback`} myVote={my_vote} />
        </div>

        <div className="space-y-6">
          {(article.categories.length > 0 || article.tags.length > 0) && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Filed under</h4>
              </div>
              <div className="card-body space-y-3">
                {article.categories.length > 0 && (
                  <div className="flex flex-wrap gap-1.5">
                    {article.categories.map((c) => (
                      <Link key={c.slug} href={`/admin/kb/category/${c.slug}`} className="badge badge-label bg-info/15 text-info hover:bg-info hover:text-white">
                        {c.name}
                      </Link>
                    ))}
                  </div>
                )}
                {article.tags.length > 0 && (
                  <div className="flex flex-wrap gap-1.5">
                    {article.tags.map((t) => (
                      <Link key={t.slug} href={`/admin/kb/tag/${t.slug}`} className="badge badge-label bg-primary/15 text-primary hover:bg-primary hover:text-white">
                        #{t.name}
                      </Link>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

          {documents.length > 0 && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Downloads</h4>
              </div>
              <div className="card-body">
                <ul className="divide-default-200 divide-y">
                  {documents.map((d) => (
                    <li key={d.id} className="py-2 first:pt-0 last:pb-0">
                      <a href={`/admin/kb/attachments/${d.id}`} className="hover:text-primary flex items-center gap-2">
                        <Icon icon="file-text" className="text-default-400 size-4.5 shrink-0" />
                        <span className="truncate font-medium">{d.name}</span>
                        <span className="text-default-400 ms-auto shrink-0 text-xs">{formatSize(d.size)}</span>
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}

          {related.length > 0 && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Related articles</h4>
              </div>
              <div className="card-body">
                <ul className="divide-default-200 divide-y">
                  {related.map((r) => (
                    <li key={r.slug} className="py-2.5 first:pt-0 last:pb-0">
                      <Link href={`/admin/kb/article/${r.slug}`} className="hover:text-primary font-medium">
                        {r.title}
                      </Link>
                      {r.summary && <p className="text-default-400 truncate text-xs">{r.summary}</p>}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
