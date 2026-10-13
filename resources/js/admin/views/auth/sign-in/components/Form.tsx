import Icon from '@/components/wrappers/Icon'
import { Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

const Form = () => {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    password: '',
    remember: false,
  })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    post('/login', { onFinish: () => reset('password') })
  }

  return (
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
            required
            onChange={(e) => setData('password', e.target.value)}
          />
        </div>
        {errors.password && <p className="text-danger mt-1 text-sm">{errors.password}</p>}
      </div>

      {/* Each phrase stays whole; on a narrow phone the link drops under the checkbox. */}
      <div className="mb-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <div className="flex min-h-11 items-center gap-2">
          <input
            className="form-checkbox form-checkbox-light size-4.25"
            type="checkbox"
            id="rememberMe"
            checked={data.remember}
            onChange={(e) => setData('remember', e.target.checked)}
          />
          <label className="form-check-label whitespace-nowrap" htmlFor="rememberMe">
            Keep me signed in
          </label>
        </div>
        <Link href="/forgot-password" className="text-default-500 inline-flex min-h-11 items-center whitespace-nowrap underline underline-offset-4">
          Forgot Password?
        </Link>
      </div>

      <div>
        <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover" disabled={processing}>
          {processing ? 'Signing In…' : 'Sign In'}
        </button>
      </div>
    </form>
  )
}

export default Form
