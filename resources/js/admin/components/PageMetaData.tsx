import { META_DATA } from '@/config/constants'

const PageMetaData = ({ title }: { title: string }) => {
  return (
    <>
      <title>{title ? `${title} | ${META_DATA.title}` : META_DATA.title}</title>
      <meta name="description" content={META_DATA.description} />
      <meta name="keywords" content={META_DATA.keywords} />
    </>
  )
}
export default PageMetaData
