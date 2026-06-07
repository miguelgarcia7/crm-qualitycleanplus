import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, useForm, usePage } from '@inertiajs/react'
import { FormEvent } from 'react'

const Page = () => {
  const user = (usePage().props as any).auth?.user
  const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch('/admin/settings/profile')
  }

  return (
    <>
      <Head title="Profile" />
      <PageBreadcrumb title="Profile" subtitle="Settings" />

      <div className="card max-w-lg rounded-2xl">
        <div className="card-body p-6">
          <form onSubmit={submit}>
            <div className="mb-4">
              <label htmlFor="name" className="form-label">Name</label>
              <input id="name" className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
              {errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}
            </div>
            <div className="mb-5">
              <label htmlFor="email" className="form-label">Email</label>
              <input id="email" type="email" className="form-input" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
              {errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}
            </div>
            <div className="flex items-center gap-3">
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
                Save
              </button>
              {recentlySuccessful && <span className="text-success text-sm">Saved.</span>}
            </div>
          </form>
        </div>
      </div>
    </>
  )
}

export default Page
