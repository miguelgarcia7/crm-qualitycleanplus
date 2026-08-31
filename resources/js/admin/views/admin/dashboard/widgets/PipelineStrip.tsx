import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type PipelineStage = {
  key: string
  label: string
  icon: string
  value: number
  note: string
  tone: 'default' | 'warning' | 'danger'
  unit: 'hours' | 'count'
  fill: number
  href?: string | null
}

export type Pipeline = {
  title: string
  subtitle?: string
  note?: string
  stages: PipelineStage[]
}

const barTone: Record<PipelineStage['tone'], string> = {
  default: 'bg-primary',
  warning: 'bg-warning',
  danger: 'bg-danger',
}

const labelTone: Record<PipelineStage['tone'], string> = {
  default: 'text-default-400',
  warning: 'text-warning',
  danger: 'text-danger',
}

/**
 * The hour-to-invoice chain, stage by stage — where the week is stuck.
 * The first stage is measured in hours; the rest count timesheets/invoices,
 * so only those share a bar scale.
 */
const PipelineStrip = ({ pipeline }: { pipeline: Pipeline }) => (
  <div className="card mb-4">
    <div className="card-header flex flex-wrap items-baseline justify-between gap-2 p-5 pb-0">
      <h4 className="card-title">
        {pipeline.title}
        {pipeline.subtitle && <span className="text-default-400 ms-2 text-sm font-normal">{pipeline.subtitle}</span>}
      </h4>
      {pipeline.note && <span className="text-default-400 text-sm">{pipeline.note}</span>}
    </div>

    <div className="card-body p-5">
      <div className="grid gap-px sm:grid-cols-2 xl:grid-cols-5">
        {pipeline.stages.map((stage) => {
          const body = (
            <>
              <span className={`flex items-center gap-2 text-sm font-medium uppercase ${labelTone[stage.tone]}`}>
                <Icon icon={stage.icon} className="size-4" />
                {stage.label}
              </span>

              <span className={`mt-2 block text-2xl font-semibold ${stage.tone === 'warning' ? 'text-warning' : ''}`}>
                {stage.value.toLocaleString()}
                {stage.unit === 'hours' && <span className="text-default-400 ms-1 text-base font-normal">h</span>}
              </span>

              <span className="text-default-400 mt-1 block text-sm">{stage.note}</span>

              <span
                className="bg-default-100 mt-3 flex h-1 w-full overflow-hidden rounded"
                role="progressbar"
                aria-label={`${stage.label} progress`}
                aria-valuenow={Math.round(stage.fill * 100)}
                aria-valuemin={0}
                aria-valuemax={100}>
                <span
                  className={`flex flex-col justify-center overflow-hidden transition duration-500 ${barTone[stage.tone]}`}
                  style={{ width: `${Math.max(4, Math.round(stage.fill * 100))}%` }}
                />
              </span>
            </>
          )

          return (
            <div key={stage.key} className="border-default-100 px-4 py-1 first:ps-0 sm:border-e last:sm:border-e-0 xl:border-e">
              {stage.href ? (
                <Link href={stage.href} className="block">
                  {body}
                </Link>
              ) : (
                body
              )}
            </div>
          )
        })}
      </div>
    </div>
  </div>
)

export default PipelineStrip
