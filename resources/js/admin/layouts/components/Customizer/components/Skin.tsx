import { useLayoutContext } from '@/context/useLayoutContext'
import { toTitleCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import auroraImg from '@/images/admin/layouts/skin-aurora.png'
import crystalImg from '@/images/admin/layouts/skin-crystal.png'
import defaultImg from '@/images/admin/layouts/skin-default.png'
import elegantImg from '@/images/admin/layouts/skin-elegant.png'
import flatImg from '@/images/admin/layouts/skin-flat.png'
import galaxyImg from '@/images/admin/layouts/skin-galaxy.png'
import luxeImg from '@/images/admin/layouts/skin-luxe.png'
import materialImg from '@/images/admin/layouts/skin-material.png'
import matrixImg from '@/images/admin/layouts/skin-matrix.png'
import minimalImg from '@/images/admin/layouts/skin-minimal.png'
import modernImg from '@/images/admin/layouts/skin-modern.png'
import monoImg from '@/images/admin/layouts/skin-mono.png'
import neoImg from '@/images/admin/layouts/skin-neo.png'
import neonImg from '@/images/admin/layouts/skin-neon.png'
import novaImg from '@/images/admin/layouts/skin-nova.png'
import orbitImg from '@/images/admin/layouts/skin-orbit.png'
import pixelImg from '@/images/admin/layouts/skin-pixel.png'
import prismImg from '@/images/admin/layouts/skin-prism.png'
import retroImg from '@/images/admin/layouts/skin-retro.png'
import saasImg from '@/images/admin/layouts/skin-saas.png'
import silverImg from '@/images/admin/layouts/skin-silver.png'
import softImg from '@/images/admin/layouts/skin-soft.png'
import vividImg from '@/images/admin/layouts/skin-vivid.png'
import xenonImg from '@/images/admin/layouts/skin-xenon.png'
import zenImg from '@/images/admin/layouts/skin-zen.png'
import sageImg from '@/images/admin/layouts/skin-sage.svg'

const skinOptions: CustomizationOptionType[] = [
  { value: 'sage', image: sageImg },
  { value: 'default', image: defaultImg },
  { value: 'minimal', image: minimalImg },
  { value: 'modern', image: modernImg },
  { value: 'material', image: materialImg },
  { value: 'saas', image: saasImg },
  { value: 'flat', image: flatImg },
  { value: 'galaxy', image: galaxyImg },
  { value: 'luxe', image: luxeImg },
  { value: 'retro', image: retroImg },
  { value: 'neon', image: neonImg },
  { value: 'pixel', image: pixelImg },
  { value: 'soft', image: softImg },
  { value: 'mono', image: monoImg },
  { value: 'prism', image: prismImg },
  { value: 'nova', image: novaImg },
  { value: 'zen', image: zenImg },
  { value: 'elegant', image: elegantImg },
  { value: 'vivid', image: vividImg },
  { value: 'aurora', image: auroraImg },
  { value: 'crystal', image: crystalImg },
  { value: 'matrix', image: matrixImg },
  { value: 'orbit', image: orbitImg },
  { value: 'neo', image: neoImg },
  { value: 'silver', image: silverImg },
  { value: 'xenon', image: xenonImg },
]

const Skin = () => {
  const { updateSettings, skin } = useLayoutContext()

  const handleSkinChange = (value: string) => {
    updateSettings({ skin: value })
  }

  return (
    <div className="p-6">
      <h5 className="text-md mb-base font-bold">Select Theme</h5>
      <div className="grid grid-cols-2 gap-3">
        {skinOptions.map((item) => (
          <div className="card-radio" key={item.value}>
            <input className="hidden" type="radio" name="data-skin" id={`demo-skin-${item.value}`} checked={skin === item.value} onChange={() => handleSkinChange(item.value)} />
            <label className="form-label" htmlFor={`demo-skin-${item.value}`}>
              <img src={item.image} alt="layout img" className="flex size-full rounded-md" />
            </label>
            <h5 className="text-md text-default-600 mt-2.5 text-center">{toTitleCase(item.value)}</h5>
          </div>
        ))}
      </div>
    </div>
  )
}

export default Skin
