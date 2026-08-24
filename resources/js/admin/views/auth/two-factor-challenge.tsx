import AuthShell from '@/components/AuthShell'
import Icon from '@/components/wrappers/Icon'
import BaseLayout from '@/layouts/BaseLayout'
import { Head, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

const Page = () => {
  const [useRecovery, setUseRecovery] = useState(false)
  const { data, setData, post, processing, errors, reset } = useForm({ code: '', recovery_code: '' })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    post('/two-factor-challenge', { onFinish: () => reset('code', 'recovery_code') })
  }

  // Fortify reads whichever field is filled, so clear the other on switch.
  const toggle = () => {
    setUseRecovery((previous) => !previous)
    reset('code', 'recovery_code')
  }

  return (
    <>
      <Head title="Two-factor authentication" />
      <AuthShell
        title="Two-factor authentication"
        subtitle={
          useRecovery
            ? 'Enter one of your recovery codes. Each code can only be used once.'
            : 'Enter the 6-digit code from your authenticator app to finish signing in.'
        }
        footer={
          <p className="text-default-400 mt-7.5 text-center">
            <button type="button" onClick={toggle} className="text-primary font-semibold underline underline-offset-4">
              {useRecovery ? 'Use an authenticator code instead' : 'Lost your device? Use a recovery code'}
            </button>
          </p>
        }>
        <form onSubmit={handleSubmit}>
          {useRecovery ? (
            <div className="mb-5">
              <label htmlFor="recoveryCode" className="form-label">
                Recovery code
                <span className="text-danger">*</span>
              </label>
              <div className="input-icon-group">
                <Icon icon="key" className="input-icon" />
                <input
                  type="text"
                  name="recovery_code"
                  className="form-input"
                  id="recoveryCode"
                  value={data.recovery_code}
                  autoComplete="one-time-code"
                  autoFocus
                  required
                  onChange={(e) => setData('recovery_code', e.target.value)}
                />
              </div>
              {errors.recovery_code && <p className="text-danger mt-1 text-sm">{errors.recovery_code}</p>}
            </div>
          ) : (
            <div className="mb-5">
              <label htmlFor="authCode" className="form-label">
                Authentication code
                <span className="text-danger">*</span>
              </label>
              <div className="input-icon-group">
                <Icon icon="device-mobile" className="input-icon" />
                <input
                  type="text"
                  name="code"
                  className="form-input"
                  id="authCode"
                  value={data.code}
                  placeholder="123456"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  autoFocus
                  required
                  onChange={(e) => setData('code', e.target.value)}
                />
              </div>
              {errors.code && <p className="text-danger mt-1 text-sm">{errors.code}</p>}
            </div>
          )}

          <div>
            <button type="submit" className="btn bg-primary hover:bg-primary-hover w-full py-3 font-semibold text-white" disabled={processing}>
              {processing ? 'Verifying…' : 'Verify'}
            </button>
          </div>
        </form>
      </AuthShell>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
