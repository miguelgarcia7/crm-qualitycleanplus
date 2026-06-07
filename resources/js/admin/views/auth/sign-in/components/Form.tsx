import Icon from '@/components/wrappers/Icon'
import { useAuth } from '@/hooks/useAuth'
import { Link } from '@inertiajs/react'
import { ChangeEvent, useState } from 'react'

const Form = () => {
  const { login, loading, error } = useAuth()

  const [form, setForm] = useState({
    email: 'admin@example.com',
    password: 'password',
  })

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e: ChangeEvent) => {
    e.preventDefault()
    await login(form.email, form.password)
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
          <input type="email" className="form-input" id="userEmail" value={form.email} placeholder="you@example.com" required onChange={handleChange} />
        </div>
      </div>

      <div className="mb-5">
        <label htmlFor="userPassword" className="form-label">
          Password
          <span className="text-danger">*</span>
        </label>
        <div className="input-icon-group">
          <Icon icon="lock-password" className="input-icon" />
          <input type="password" className="form-input" id="userPassword" value={form.password} placeholder="••••••••" required onChange={handleChange} />
        </div>
      </div>

      <div className="mb-5 flex items-center justify-between">
        <div className="flex items-start gap-2 lg:items-center">
          <input className="form-checkbox form-checkbox-light mt-1 size-4.25 lg:mt-0" type="checkbox" id="rememberMe" />
          <label className="form-check-label" htmlFor="rememberMe">
            Keep me signed in
          </label>
        </div>
        <Link href="/auth/card/reset-pass" className="text-default-400 underline underline-offset-4">
          Forgot Password?
        </Link>
      </div>
      {error && <p className="text-danger">{error}</p>}
      <div>
        <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover" disabled={loading}>
          Sign In
        </button>
      </div>
    </form>
  )
}

export default Form
