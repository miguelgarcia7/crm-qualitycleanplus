import AuthShell from '@/components/AuthShell'
import PasswordInputWithStrength from '@/components/PasswordInputWithStrength'
import Icon from '@/components/wrappers/Icon'
import BaseLayout from '@/layouts/BaseLayout'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Props = { email?: string | null; token: string }

const Page = ({ email, token }: Props) => {
  const { data, setData, post, processing, errors, reset } = useForm({
    token,
    email: email ?? '',
    password: '',
    password_confirmation: '',
  })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    post('/reset-password', { onFinish: () => reset('password', 'password_confirmation') })
  }

  return (
    <>
      <Head title="Set your password" />
      <AuthShell
        title="Set your password"
        subtitle="Choose a password for your account. You’ll use it together with your email address to sign in."
        footer={
          <p className="text-default-400 mt-7.5 text-center">
            Return to&nbsp;
            <Link href="/login" className="text-primary font-semibold underline underline-offset-4">
              Sign in
            </Link>
          </p>
        }>
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
                autoComplete="username"
                required
                onChange={(e) => setData('email', e.target.value)}
              />
            </div>
            {errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}
          </div>

          <div className="mb-5">
            <PasswordInputWithStrength
              id="userPassword"
              name="password"
              label="New password"
              placeholder="••••••••"
              showIcon
              password={data.password}
              setPassword={(value) => setData('password', value)}
            />
            {errors.password && <p className="text-danger mt-1 text-sm">{errors.password}</p>}
          </div>

          <div className="mb-5">
            <label htmlFor="userPasswordConfirm" className="form-label">
              Confirm password
              <span className="text-danger">*</span>
            </label>
            <div className="input-icon-group">
              <Icon icon="lock-password" className="input-icon" />
              <input
                type="password"
                name="password_confirmation"
                className="form-input"
                id="userPasswordConfirm"
                value={data.password_confirmation}
                placeholder="••••••••"
                autoComplete="new-password"
                required
                onChange={(e) => setData('password_confirmation', e.target.value)}
              />
            </div>
          </div>

          <div>
            <button type="submit" className="btn bg-primary hover:bg-primary-hover w-full py-3 font-semibold text-white" disabled={processing}>
              {processing ? 'Saving…' : 'Save password'}
            </button>
          </div>
        </form>
      </AuthShell>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
