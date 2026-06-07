import Icon from '@/components/wrappers/Icon'

const SearchBoxRounded = () => {
  return (
    <div id="search-box-rounded" className="hidden xl:flex">
      <div className="input-icon-group">
        <Icon icon="search" className="input-icon text-lg text-(--topbar-item-color)/50! placeholder:opacity-50" />
        <input type="search" id="topbar-search" className="form-input w-57.5 rounded-full! border-(--topbar-search-border)! bg-(--topbar-search-bg)! text-(--topbar-item-color)! placeholder:opacity-50" placeholder="Quick Search..." />
      </div>
    </div>
  )
}

export default SearchBoxRounded
