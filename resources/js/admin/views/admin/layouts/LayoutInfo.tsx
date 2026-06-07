import Icon from '@/components/wrappers/Icon'

type LayoutInfoProps = {
  option: string
  value: string
}

const LayoutInfo = ({ option, value }: LayoutInfoProps) => {
  return (
    <>
      <div className="container-fluid">
        <div className="bg-info/10 text-info border-s-3 border-info rounded-md flex items-start gap-3 py-3 px-4 mb-4">
          <div>
            <Icon icon="info-circle" className="text-xl" />
          </div>
          <div>
            To enable this layout, use <code>updateSettings</code> from <code>useLayoutContext</code> context hook and pass it
            <code>{`{${option}:'${value}'}`}</code>. eg. <code>{`updateSettings({ ${option}: '${value}' })`}</code>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <h4 className="text-lg">Your custom content here</h4>
          </div>
        </div>
      </div>
    </>
  )
}

export default LayoutInfo
