import { router } from '@inertiajs/react'
import { useState } from 'react'
import { useSessionStorage } from 'usehooks-ts'
export const useAuth = () => {
  

  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [token, setToken, removeToken] = useSessionStorage<string | null>('token', null)

  const dummyUser = {
    email: 'admin@example.com',
    password: 'password',
    token: 'auth-token',
  }

  const login = (email: string, password: string) => {
    try {
      setLoading(true)
      setError(null)

      if (email === dummyUser.email && password === dummyUser.password) {
        setToken(dummyUser.token)
        router.visit('/')
      } else {
        throw new Error('Invalid email or password')
      }
    } catch (err: any) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const logout = () => {
    removeToken()
    router.visit('/auth/sign-in')
  }

  const isAuthenticated = token

  return {
    login,
    logout,
    isAuthenticated,
    loading,
    error,
  }
}
