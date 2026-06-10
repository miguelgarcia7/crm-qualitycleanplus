import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import Quill from '@/components/wrappers/Quill'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { useRef, useState } from 'react'

type ArticleForm = {
  id: number
  slug: string
  title: string
  summary: string | null
  content: string
  status: string
  status_label: string
  version: number
  is_featured: boolean
  categories: number[]
  tags: string[]
  roles: number[]
} | null

type Attachment = {
  id: number
  name: string
  size: number
  mime_type: string | null
  is_image: boolean
}

type Props = {
  article: ArticleForm
  attachments?: Attachment[]
  categoryOptions: { id: number; name: string; depth: number }[]
  tagSuggestions: string[]
  roleOptions: { id: number; name: string }[] | null
  canManageVisibility: boolean
}

// No image/video embeds — pictures travel as attachments, not base64 blobs in
// the article body (the content column is capped server-side).
const quillModules = {
  toolbar: [
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ script: 'super' }, { script: 'sub' }],
    [{ header: [false, 1, 2, 3, 4] }],
    ['blockquote', 'code-block'],
    [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
    [{ align: [] }],
    ['link'],
    ['clean'],
  ],
}

const roleLabel = (name: string) => name.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())

const formatSize = (bytes: number) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`)

const Page = ({ article, attachments = [], categoryOptions, tagSuggestions, roleOptions, canManageVisibility }: Props) => {
  const { data, setData, post, put, processing, errors } = useForm({
    title: article?.title ?? '',
    summary: article?.summary ?? '',
    content: article?.content ?? '',
    is_featured: article?.is_featured ?? false,
    categories: article?.categories ?? ([] as number[]),
    tags: article?.tags ?? ([] as string[]),
    roles: article?.roles ?? ([] as number[]),
    change_summary: '',
  })

  const [tagInput, setTagInput] = useState('')
  const fileInput = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (article) {
      put(`/admin/kb/articles/${article.slug}`)
    } else {
      post('/admin/kb/articles')
    }
  }

  const toggleId = (field: 'categories' | 'roles', id: number) => {
    const current = data[field]
    setData(field, current.includes(id) ? current.filter((c) => c !== id) : [...current, id])
  }

  const addTag = (raw: string) => {
    const name = raw.trim().replace(/,+$/, '')
    if (name !== '' && !data.tags.some((t) => t.toLowerCase() === name.toLowerCase())) {
      setData('tags', [...data.tags, name])
    }
    setTagInput('')
  }

  const onTagKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault()
      addTag(tagInput)
    } else if (e.key === 'Backspace' && tagInput === '' && data.tags.length > 0) {
      setData('tags', data.tags.slice(0, -1))
    }
  }

  const uploadAttachment = (file: File | undefined) => {
    if (!article || !file) return
    setUploading(true)
    router.post(
      `/admin/kb/articles/${article.slug}/attachments`,
      { attachment: file },
      {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
          setUploading(false)
          if (fileInput.current) fileInput.current.value = ''
        },
      },
    )
  }

  const removeAttachment = (a: Attachment) => {
    if (confirm(`Remove "${a.name}"?`)) {
      router.delete(`/admin/kb/attachments/${a.id}`, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title={article ? `Edit: ${article.title}` : 'New Article'} />
      <PageBreadcrumb title={article ? 'Edit Article' : 'New Article'} subtitle="Knowledge Base" />

      <form onSubmit={submit}>
        <div className="gap-base grid xl:grid-cols-3">
          <div className="space-y-6 xl:col-span-2">
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Content</h4>
                {article && (
                  <span className="text-default-400 text-sm">
                    v{article.version} · {article.status_label}
                  </span>
                )}
              </div>
              <div className="card-body space-y-4">
                <div>
                  <label className="form-label">Title</label>
                  <input className="form-input w-full" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                  {errors.title && <p className="text-danger mt-1 text-sm">{errors.title}</p>}
                </div>
                <div>
                  <label className="form-label">Summary</label>
                  <textarea
                    className="form-input w-full"
                    rows={2}
                    value={data.summary}
                    onChange={(e) => setData('summary', e.target.value)}
                    placeholder="One or two sentences shown in lists and search results."
                  />
                  {errors.summary && <p className="text-danger mt-1 text-sm">{errors.summary}</p>}
                </div>
                <div>
                  <label className="form-label">Body</label>
                  <Quill theme="snow" modules={quillModules} value={data.content} onChange={(value: string) => setData('content', value)} />
                  {errors.content && <p className="text-danger mt-1 text-sm">{errors.content}</p>}
                </div>
                {article && (
                  <div>
                    <label className="form-label">Change summary (optional)</label>
                    <input
                      className="form-input w-full"
                      value={data.change_summary}
                      onChange={(e) => setData('change_summary', e.target.value)}
                      placeholder="What changed in this edit — shown in the version history."
                    />
                    {errors.change_summary && <p className="text-danger mt-1 text-sm">{errors.change_summary}</p>}
                  </div>
                )}
              </div>
            </div>

            {article && (
              <div className="card">
                <div className="card-header">
                  <h4 className="card-title">Attachments</h4>
                  <label className={cn('btn btn-sm bg-primary/15 text-primary hover:bg-primary cursor-pointer hover:text-white', uploading && 'pointer-events-none opacity-50')}>
                    <Icon icon="cloud-upload" className="me-1 size-4" />
                    {uploading ? 'Uploading…' : 'Add file'}
                    <input ref={fileInput} type="file" className="hidden" onChange={(e) => uploadAttachment(e.target.files?.[0])} />
                  </label>
                </div>
                <div className="card-body">
                  {attachments.length === 0 ? (
                    <p className="text-default-400 text-sm">No attachments. Images show inline on the article; documents become download links.</p>
                  ) : (
                    <ul className="divide-default-200 divide-y">
                      {attachments.map((a) => (
                        <li key={a.id} className="flex items-center justify-between gap-3 py-2">
                          <div className="flex min-w-0 items-center gap-2">
                            <Icon icon={a.is_image ? 'photo' : 'file-text'} className="text-default-400 size-4.5 shrink-0" />
                            <span className="truncate font-medium">{a.name}</span>
                            <span className="text-default-400 shrink-0 text-xs">{formatSize(a.size)}</span>
                          </div>
                          <div className="flex shrink-0 gap-1.5">
                            <a
                              href={`/admin/kb/attachments/${a.id}`}
                              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                              title="Download"
                            >
                              <Icon icon="download" className="text-base" />
                            </a>
                            <button
                              type="button"
                              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                              onClick={() => removeAttachment(a)}
                              title="Remove"
                            >
                              <Icon icon="trash" className="text-base" />
                            </button>
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </div>
            )}
          </div>

          <div className="space-y-6">
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Publish</h4>
              </div>
              <div className="card-body space-y-4">
                <div className="form-check">
                  <input
                    id="is_featured"
                    type="checkbox"
                    className="form-checkbox"
                    checked={data.is_featured}
                    onChange={(e) => setData('is_featured', e.target.checked)}
                  />
                  <label htmlFor="is_featured" className="form-check-label ms-2">
                    Featured article
                  </label>
                </div>
                <div className="flex gap-2">
                  <button className="btn bg-primary hover:bg-primary-hover flex-1 py-2 font-semibold text-white" disabled={processing}>
                    {article ? 'Save changes' : 'Create draft'}
                  </button>
                  <Link href={article ? `/admin/kb/articles/${article.slug}` : '/admin/kb/articles'} className="btn btn-light">
                    Cancel
                  </Link>
                </div>
                {!article && <p className="text-default-400 text-xs">New articles start as drafts — publish from the article page.</p>}
              </div>
            </div>

            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Categories</h4>
              </div>
              <div className="card-body">
                {categoryOptions.length === 0 ? (
                  <p className="text-default-400 text-sm">
                    No categories yet —{' '}
                    <Link href="/admin/kb/categories" className="text-primary">
                      create one
                    </Link>{' '}
                    first.
                  </p>
                ) : (
                  <div className="max-h-56 space-y-1.5 overflow-y-auto">
                    {categoryOptions.map((c) => (
                      <div key={c.id} className="form-check" style={{ marginInlineStart: `${c.depth * 1.25}rem` }}>
                        <input
                          id={`cat-${c.id}`}
                          type="checkbox"
                          className="form-checkbox"
                          checked={data.categories.includes(c.id)}
                          onChange={() => toggleId('categories', c.id)}
                        />
                        <label htmlFor={`cat-${c.id}`} className="form-check-label ms-2">
                          {c.name}
                        </label>
                      </div>
                    ))}
                  </div>
                )}
                {errors.categories && <p className="text-danger mt-2 text-sm">{errors.categories}</p>}
              </div>
            </div>

            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Tags</h4>
              </div>
              <div className="card-body">
                {data.tags.length > 0 && (
                  <div className="mb-2 flex flex-wrap gap-1.5">
                    {data.tags.map((tag) => (
                      <span key={tag} className="badge badge-label bg-primary/15 text-primary inline-flex items-center gap-1">
                        {tag}
                        <button type="button" onClick={() => setData('tags', data.tags.filter((t) => t !== tag))} title={`Remove ${tag}`}>
                          <Icon icon="x" className="size-3" />
                        </button>
                      </span>
                    ))}
                  </div>
                )}
                <input
                  className="form-input w-full"
                  list="kb-tag-suggestions"
                  value={tagInput}
                  onChange={(e) => setTagInput(e.target.value)}
                  onKeyDown={onTagKeyDown}
                  onBlur={() => addTag(tagInput)}
                  placeholder="Type a tag, press Enter"
                />
                <datalist id="kb-tag-suggestions">
                  {tagSuggestions
                    .filter((t) => !data.tags.includes(t))
                    .map((t) => (
                      <option key={t} value={t} />
                    ))}
                </datalist>
                <p className="text-default-400 mt-1.5 text-xs">New tags are created automatically.</p>
              </div>
            </div>

            {canManageVisibility && roleOptions && (
              <div className="card">
                <div className="card-header">
                  <h4 className="card-title">Visibility</h4>
                </div>
                <div className="card-body">
                  <p className="text-default-400 mb-2 text-sm">No roles selected = visible to everyone who can read the KB.</p>
                  <div className="space-y-1.5">
                    {roleOptions.map((r) => (
                      <div key={r.id} className="form-check">
                        <input
                          id={`role-${r.id}`}
                          type="checkbox"
                          className="form-checkbox"
                          checked={data.roles.includes(r.id)}
                          onChange={() => toggleId('roles', r.id)}
                        />
                        <label htmlFor={`role-${r.id}`} className="form-check-label ms-2">
                          {roleLabel(r.name)}
                        </label>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </form>
    </>
  )
}

export default Page
