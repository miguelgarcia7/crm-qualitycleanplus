import AuthShell from '@/components/AuthShell'
import Icon from '@/components/wrappers/Icon'
import BaseLayout from '@/layouts/BaseLayout'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Props = { status?: string | null }

const Page = ({ status }: Props) => {
  const { data, setData, post, processing, errors } = useForm({ email: '' })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    post('/forgot-password')
  }

  return (
    <>
      <Head title="Forgot password" />
      <AuthShell
        title="Forgot your password?"
        subtitle="Enter your email address and we’ll send you a link to set a new one."
        footer={
          <p className="text-default-400 mt-7.5 text-center">
            Return to&nbsp;
            <Link href="/login" className="text-primary font-semibold underline underline-offset-4">
              Sign in
            </Link>
          </p>
        }>
        {status && <div className="bg-success/15 text-success mb-5 rounded-lg px-4 py-3 text-sm">{status}</div>}

        <form onSubmit={handleSubmit}>
          <div className="mb-5">
            <label htmlFor="userEmail" className="form-label">
              Email address
              <span className="text-danger">*</span>
            </label>
            <div className="input-icon-group">
              <Icon icon="mail" className="input-icon" />
              <input
                type="email"
                name="email"
                className="form-input"
                id="userEmail"
                value={data.email}
                placeholder="you@example.com"
                autoComplete="username"
                autoFocus
                required
                onChange={(e) => setData('email', e.target.value)}
              />
            </div>
            {errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}
          </div>

          <div>
            <button type="submit" className="btn bg-primary hover:bg-primary-hover w-full py-3 font-semibold text-white" disabled={processing}>
              {processing ? 'Sending…' : 'Email password reset link'}
            </button>
          </div>
        </form>
      </AuthShell>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
