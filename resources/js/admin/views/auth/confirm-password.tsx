import AuthShell from '@/components/AuthShell'
import Icon from '@/components/wrappers/Icon'
import BaseLayout from '@/layouts/BaseLayout'
import { Head, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

const Page = () => {
  const { data, setData, post, processing, errors, reset } = useForm({ password: '' })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    post('/user/confirm-password', { onFinish: () => reset('password') })
  }

  return (
    <>
      <Head title="Confirm password" />
      <AuthShell title="Confirm your password" subtitle="This is a secure area. Please re-enter your password to continue.">
        <form onSubmit={handleSubmit}>
          <div className="mb-5">
            <label htmlFor="userPassword" className="form-label">
              Password
              <span className="text-danger">*</span>
            </label>
            <div className="input-icon-group">
              <Icon icon="lock-password" className="input-icon" />
              <input
                type="password"
                name="password"
                className="form-input"
                id="userPassword"
                value={data.password}
                placeholder="••••••••"
                autoComplete="current-password"
                autoFocus
                required
                onChange={(e) => setData('password', e.target.value)}
              />
            </div>
            {errors.password && <p className="text-danger mt-1 text-sm">{errors.password}</p>}
          </div>

          <div>
            <button type="submit" className="btn bg-primary hover:bg-primary-hover w-full py-3 font-semibold text-white" disabled={processing}>
              {processing ? 'Confirming…' : 'Confirm'}
            </button>
          </div>
        </form>
      </AuthShell>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
