import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import FeedbackWidget from '@/views/admin/kb/components/FeedbackWidget'
import { Head, Link } from '@inertiajs/react'

type Props = {
  article: {
    slug: string
    title: string
    summary: string | null
    content: string
    published_at: string | null
    categories: string[]
  }
  attachments: { id: number; name: string; is_image: boolean }[]
  my_vote: string | null
}

const Page = ({ article, attachments, my_vote }: Props) => {
  const images = attachments.filter((a) => a.is_image)
  const documents = attachments.filter((a) => !a.is_image)

  return (
    <>
      <Head title={article.title} />
      <PageBreadcrumb title={article.title} subtitle="Knowledge Base" />

      <div className="mb-4">
        <Link href="/kb" className="btn btn-sm btn-light">
          ← All articles
        </Link>
      </div>

      <div className="card mb-4">
        <div className="card-header block">
          <h4 className="card-title mb-1.25">{article.title}</h4>
          <p className="text-default-400 text-sm">
            {article.categories.join(', ')}
            {article.published_at && ` · ${article.published_at}`}
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
                <a key={img.id} href={`/kb/attachments/${img.id}`} title={img.name}>
                  <img src={`/kb/attachments/${img.id}`} alt={img.name} className="border-default-200 w-full rounded-md border" />
                </a>
              ))}
            </div>
          )}

          {documents.length > 0 && (
            <ul className="divide-default-200 border-default-200 mt-6 divide-y border-t pt-3">
              {documents.map((d) => (
                <li key={d.id} className="py-2">
                  <a href={`/kb/attachments/${d.id}`} className="hover:text-primary flex items-center gap-2">
                    <Icon icon="download" className="text-default-400 size-4.5 shrink-0" />
                    <span className="truncate font-medium">{d.name}</span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <FeedbackWidget endpoint={`/kb/${article.slug}/feedback`} myVote={my_vote} />
    </>
  )
}

export default Page
