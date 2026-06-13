import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, usePage } from '@inertiajs/react'

type Props = {
  article: {
    id: number
    slug: string
    title: string
    summary: string | null
    content: string
    status: string
    status_label: string
    version: number
    is_featured: boolean
    view_count: number
    author: string
    last_editor: string | null
    categories: { id: number; name: string; slug: string }[]
    tags: { id: number; name: string; slug: string }[]
    roles: string[]
    published_at: string | null
    created_at: string | null
    updated_at: string | null
  }
  attachments: { id: number; name: string; size: number; mime_type: string | null; is_image: boolean }[]
  versions: { version: number; title: string; author: string; change_summary: string | null; created_at: string | null }[]
  feedback_stats: { helpful: number; not_helpful: number; open: number }
  can: { update: boolean; publish: boolean; delete: boolean }
}

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  published: 'bg-success/15 text-success',
  archived: 'bg-secondary/15 text-secondary',
}

const roleLabel = (name: string) => name.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())

const formatSize = (bytes: number) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`)

const Field = ({ label, children }: { label: string; children: React.ReactNode }) => (
  <div>
    <p className="text-default-400 mb-1.25 font-medium">{label}</p>
    <p>{children}</p>
  </div>
)

const Page = ({ article, attachments, versions, feedback_stats, can }: Props) => {
  const errors = usePage().props.errors as Record<string, string>

  const transition = (action: 'publish' | 'unpublish' | 'archive', message: string) => {
    if (confirm(message)) {
      router.post(`/admin/kb/articles/${article.slug}/${action}`, {}, { preserveScroll: true })
    }
  }

  const destroy = () => {
    if (confirm(`Delete "${article.title}"? The version history goes with it.`)) {
      router.delete(`/admin/kb/articles/${article.slug}`)
    }
  }

  return (
    <>
      <Head title={article.title} />
      <PageBreadcrumb title={article.title} subtitle="Knowledge Base" />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Link href="/admin/kb/articles" className="btn btn-sm btn-light">
          ← Articles
        </Link>
        <span className={cn('badge badge-label', statusBadge[article.status] ?? 'bg-light text-default-600')}>{article.status_label}</span>
        {article.is_featured && (
          <span className="badge badge-label bg-warning/15 text-warning">
            <Icon icon="star-filled" className="me-1 inline size-3" />
            Featured
          </span>
        )}
        <span className="grow" />
        {can.update && (
          <Link href={`/admin/kb/articles/${article.slug}/edit`} className="btn btn-sm bg-primary text-white">
            <Icon icon="edit" className="me-1 size-4" /> Edit
          </Link>
        )}
        {can.publish && article.status !== 'published' && article.status !== 'archived' && (
          <button className="btn btn-sm bg-success text-white" onClick={() => transition('publish', 'Publish this article to its readers?')}>
            Publish
          </button>
        )}
        {can.publish && article.status === 'published' && (
          <button
            className="btn btn-sm bg-warning/15 text-warning hover:bg-warning hover:text-white"
            onClick={() => transition('unpublish', 'Unpublish? Readers lose access until it is published again.')}
          >
            Unpublish
          </button>
        )}
        {can.publish && article.status === 'archived' && (
          <button
            className="btn btn-sm bg-primary/15 text-primary hover:bg-primary hover:text-white"
            onClick={() => transition('unpublish', 'Unarchive this article back to draft?')}
          >
            Unarchive
          </button>
        )}
        {can.publish && article.status !== 'archived' && (
          <button
            className="btn btn-sm bg-secondary/15 text-secondary hover:bg-secondary hover:text-white"
            onClick={() => transition('archive', 'Archive? The article becomes read-only and hidden from readers.')}
          >
            Archive
          </button>
        )}
        {can.delete && (
          <button className="btn btn-sm bg-danger/15 text-danger hover:bg-danger hover:text-white" onClick={destroy}>
            Delete
          </button>
        )}
      </div>

      {Object.keys(errors).length > 0 && (
        <div className="card border-danger mb-4 border">
          <div className="card-body text-danger p-4 text-sm">
            {Object.values(errors).map((message, i) => (
              <p key={i}>{message}</p>
            ))}
          </div>
        </div>
      )}

      <div className="gap-base grid xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <div className="card">
            <div className="card-body">
              {article.summary && <p className="text-default-500 border-default-200 mb-4 border-b pb-4 italic">{article.summary}</p>}
              <div className="ql-snow">
                <div className="ql-editor !p-0" dangerouslySetInnerHTML={{ __html: article.content }} />
              </div>
            </div>
          </div>

          {attachments.length > 0 && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Attachments</h4>
              </div>
              <div className="card-body">
                <ul className="divide-default-200 divide-y">
                  {attachments.map((a) => (
                    <li key={a.id} className="flex items-center justify-between gap-3 py-2">
                      <div className="flex min-w-0 items-center gap-2">
                        <Icon icon={a.is_image ? 'photo' : 'file-text'} className="text-default-400 size-4.5 shrink-0" />
                        <span className="truncate font-medium">{a.name}</span>
                        <span className="text-default-400 shrink-0 text-xs">{formatSize(a.size)}</span>
                      </div>
                      <a
                        href={`/admin/kb/attachments/${a.id}`}
                        className="btn btn-icon border-default-300 hover:border-default-400 shrink-0 border"
                        title="Download"
                      >
                        <Icon icon="download" className="text-base" />
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}
        </div>

        <div className="space-y-6">
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Details</h4>
            </div>
            <div className="card-body grid grid-cols-2 gap-4">
              <Field label="Author">{article.author}</Field>
              <Field label="Last edited by">{article.last_editor ?? '—'}</Field>
              <Field label="Version">v{article.version}</Field>
              <Field label="Views">{article.view_count}</Field>
              <Field label="Published">{article.published_at ?? 'Not published'}</Field>
              <Field label="Updated">{article.updated_at ?? '—'}</Field>
              <div className="col-span-2">
                <p className="text-default-400 mb-1.25 font-medium">Categories</p>
                <div className="flex flex-wrap gap-1.5">
                  {article.categories.length === 0 ? (
                    <span>—</span>
                  ) : (
                    article.categories.map((c) => (
                      <span key={c.id} className="badge badge-label bg-info/15 text-info">
                        {c.name}
                      </span>
                    ))
                  )}
                </div>
              </div>
              <div className="col-span-2">
                <p className="text-default-400 mb-1.25 font-medium">Tags</p>
                <div className="flex flex-wrap gap-1.5">
                  {article.tags.length === 0 ? (
                    <span>—</span>
                  ) : (
                    article.tags.map((t) => (
                      <span key={t.id} className="badge badge-label bg-primary/15 text-primary">
                        {t.name}
                      </span>
                    ))
                  )}
                </div>
              </div>
              <div className="col-span-2">
                <p className="text-default-400 mb-1.25 font-medium">Visible to</p>
                <div className="flex flex-wrap gap-1.5">
                  {article.roles.length === 0 ? (
                    <span className="badge badge-label bg-success/15 text-success">All KB readers</span>
                  ) : (
                    article.roles.map((r) => (
                      <span key={r} className="badge badge-label bg-secondary/15 text-secondary">
                        {roleLabel(r)}
                      </span>
                    ))
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Feedback</h4>
              <Link href="/admin/kb/feedback" className="text-primary text-sm">
                Queue →
              </Link>
            </div>
            <div className="card-body grid grid-cols-3 gap-3 text-center">
              <div>
                <p className="text-success text-xl font-semibold">{feedback_stats.helpful}</p>
                <p className="text-default-400 text-xs">Helpful</p>
              </div>
              <div>
                <p className="text-danger text-xl font-semibold">{feedback_stats.not_helpful}</p>
                <p className="text-default-400 text-xs">Not helpful</p>
              </div>
              <div>
                <p className="text-warning text-xl font-semibold">{feedback_stats.open}</p>
                <p className="text-default-400 text-xs">Open items</p>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Version history</h4>
            </div>
            {versions.length === 0 ? (
              <div className="card-body">
                <p className="text-default-400 text-sm">No earlier versions — snapshots appear here after the first edit.</p>
              </div>
            ) : (
              <div className="table-wrapper">
                <table className="table">
                  <thead className="thead-sm bg-light/25 text-xs uppercase">
                    <tr>
                      <th>Version</th>
                      <th>By</th>
                      <th>When</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {versions.map((v) => (
                      <tr key={v.version}>
                        <td>
                          <span className="font-semibold">v{v.version}</span>
                          {v.change_summary && <p className="text-default-400 max-w-40 truncate text-xs">{v.change_summary}</p>}
                        </td>
                        <td>{v.author}</td>
                        <td className="text-default-400 text-xs">{v.created_at}</td>
                        <td>
                          <Link
                            href={`/admin/kb/articles/${article.slug}/versions/${v.version}`}
                            className="btn btn-icon border-default-300 hover:border-default-400 border"
                            title={`View version ${v.version}`}
                          >
                            <Icon icon="eye" className="text-base" />
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
