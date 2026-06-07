import PasswordInputWithStrength from '@/components/PasswordInputWithStrength'
import Icon from '@/components/wrappers/Icon'
import { META_DATA } from '@/config/constants'
import { useState } from 'react'

const SignUpForm = () => {
  const [password, setPassword] = useState('')

  return (
    <form className="mt-9">
      <div className="mb-6">
        <label htmlFor="userName" className="form-label">
          Full Name
          <span className="text-danger">*</span>
        </label>
        <div className="input-icon-group">
          <Icon icon="user" className="input-icon" />
          <input type="text" className="form-input" id="userName" placeholder={META_DATA.username} required />
        </div>
      </div>

      <div className="mb-6">
        <label htmlFor="userEmail" className="form-label">
          Email address
          <span className="text-danger">*</span>
        </label>
        <div className="input-icon-group">
          <Icon icon="mail" className="input-icon" />
          <input type="email" className="form-input" id="userEmail" placeholder="you@example.com" required />
        </div>
      </div>

      <div className="mb-5" data-password="bar">
        <label htmlFor="userPassword" className="form-label">
          Password
          <span className="text-danger">*</span>
        </label>
        <PasswordInputWithStrength password={password} setPassword={setPassword} placeholder="••••••••" showIcon />
      </div>

      <div className="mb-6">
        <div className="flex items-center gap-2">
          <input className="form-checkbox form-checkbox-light size-4.5" type="checkbox" id="termAndPolicy" />
          <label className="form-check-label" htmlFor="termAndPolicy">
            Agree the Terms & Policy
          </label>
        </div>
      </div>

      <div>
        <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover">
          Create Account
        </button>
      </div>
    </form>
  )
}

export default SignUpForm
