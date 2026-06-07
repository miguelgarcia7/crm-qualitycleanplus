import PasswordInputWithStrength from '@/components/PasswordInputWithStrength'
import { useState } from 'react'

const NewPassForm = () => {
  const [password, setPassword] = useState('')
  return (
    <form>
      <div className="mb-5">
        <label htmlFor="userEmail" className="form-label">
          Email address
          <span className="text-danger">*</span>
        </label>
        <div className="input-group">
          <input type="email" className="form-input" id="userEmail" placeholder="you@example.com" required />
        </div>
      </div>

      <div className="mb-5">
        <label htmlFor="userEmail" className="form-label">
          Enter your 6-digit code <span className="text-danger">*</span>
        </label>
        <div className="two-factor flex gap-2">
          <input type="text" className="form-input text-center" required />
          <input type="text" className="form-input text-center" required />
          <input type="text" className="form-input text-center" required />
          <input type="text" className="form-input text-center" required />
          <input type="text" className="form-input text-center" required />
          <input type="text" className="form-input text-center" required />
        </div>
      </div>

      <div className="mb-5" data-password="bar">
        <label htmlFor="userPassword" className="form-label">
          Password <span className="text-danger">*</span>
        </label>
        <PasswordInputWithStrength password={password} setPassword={setPassword} placeholder="••••••••" />
      </div>

      <div className="mb-5">
        <label htmlFor="userNewPassword" className="form-label">
          Confirm New Password
          <span className="text-danger">*</span>
        </label>
        <div className="input-group">
          <input type="password" className="form-input" id="userNewPassword" placeholder="••••••••" required />
        </div>
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
          Update Password
        </button>
      </div>
    </form>
  )
}

export default NewPassForm
