import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type Props = {
  article: { slug: string; title: string; version: number }
  snapshot: {
    version: number
    title: string
    summary: string | null
    content: string
    author: string
    change_summary: string | null
    created_at: string | null
  }
}

const Page = ({ article, snapshot }: Props) => (
  <>
    <Head title={`v${snapshot.version} — ${snapshot.title}`} />
    <PageBreadcrumb title={`Version ${snapshot.version}`} subtitle="Knowledge Base" />

    <div className="mb-4 flex flex-wrap items-center gap-3">
      <Link href={`/admin/kb/articles/${article.slug}`} className="btn btn-sm btn-light">
        ← Back to article (v{article.version})
      </Link>
      <span className="badge badge-label bg-secondary/15 text-secondary">Read-only snapshot</span>
    </div>

    <div className="card">
      <div className="card-header block">
        <h4 className="card-title mb-1.25">{snapshot.title}</h4>
        <p className="text-default-400 text-sm">
          v{snapshot.version} by {snapshot.author}
          {snapshot.created_at && ` · ${snapshot.created_at}`}
          {snapshot.change_summary && ` · “${snapshot.change_summary}”`}
        </p>
      </div>
      <div className="card-body">
        {snapshot.summary && <p className="text-default-500 border-default-200 mb-4 border-b pb-4 italic">{snapshot.summary}</p>}
        <div className="ql-snow">
          <div className="ql-editor !p-0" dangerouslySetInnerHTML={{ __html: snapshot.content }} />
        </div>
      </div>
    </div>
  </>
)

export default Page
