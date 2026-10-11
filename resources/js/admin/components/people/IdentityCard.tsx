import Icon from '@/components/wrappers/Icon'
import { ReactNode } from 'react'

export const initialsOf = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

/** A person's photo, or their initials when there is none. */
export const PersonAvatar = ({ name, avatar }: { name: string; avatar: string | null }) =>
  avatar ? (
    <img src={avatar} alt={name} className="size-18 shrink-0 rounded-full object-cover" />
  ) : (
    <div className="bg-primary/10 text-primary flex size-18 shrink-0 items-center justify-center rounded-full text-xl font-semibold">{initialsOf(name)}</div>
  )

/** One labelled fact on the identity card (email, phone, hire date, …). */
export const FactRow = ({ icon, label, children }: { icon: string; label: string; children: ReactNode }) => (
  <div className="flex items-center gap-3">
    <div>
      <div className="btn btn-icon bg-light size-8!">
        <Icon icon={icon} className="text-secondary text-lg" />
      </div>
    </div>
    <p className="text-sm">
      {label} <span className="text-dark font-semibold">{children}</span>
    </p>
  </div>
)

/**
 * The HRM identity card shared by My Profile and a person's profile: photo,
 * name, what they are, status badges, then labelled facts and an optional note.
 */
const IdentityCard = ({
  avatar,
  name,
  subtitle,
  badges,
  children,
  footer,
}: {
  avatar: ReactNode
  name: string
  subtitle: string
  badges: ReactNode
  children: ReactNode
  footer?: ReactNode
}) => (
  <div className="card mb-0">
    <div className="card-body">
      <div className="mb-7.5 flex items-center">
        <div className="gap-base flex items-center">
          {avatar}
          <div>
            <h5 className="font-medium">{name}</h5>
            <p className="text-default-400 mb-3">{subtitle}</p>
            <div className="flex flex-wrap gap-1.5">{badges}</div>
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-y-3">{children}</div>

      {footer && <p className="text-default-400 border-default-200 mt-6 border-t pt-4 text-xs">{footer}</p>}
    </div>
  </div>
)

export default IdentityCard
