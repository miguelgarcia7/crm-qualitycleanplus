import { ReactNode } from 'react'

type DeleteConfirmationModalProps = {
  onConfirm: () => void
  selectedCount: number
  itemName?: string
  confirmButtonVariant?: string
  cancelButtonVariant?: string
  modalTitle?: string
  confirmButtonText?: string
  cancelButtonText?: string
  children?: ReactNode
}

const DeleteConfirmationModal = ({ onConfirm, selectedCount, itemName = 'row', modalTitle = 'Confirm Deletion', confirmButtonText = 'Delete', cancelButtonText = 'Cancel', children }: DeleteConfirmationModalProps) => {
  const getConfirmationMessage = () => {
    if (children) return children

    if (selectedCount > 1) {
      return `Are you sure you want to delete these ${selectedCount} ${itemName}s?`
    }
    return `Are you sure you want to delete this ${itemName}?`
  }

  return (
    <>
      <div id="confirm-delete-modal" className="hs-overlay pointer-events-none fixed start-0 top-0 z-80  size-full overflow-x-hidden overflow-y-auto" tabIndex={-1} aria-hidden="true">
        <div className="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-lg">
          <div className="border-default-300 pointer-events-auto flex w-full flex-col rounded-md border bg-card">
            <div className="border-default-300 flex items-center justify-between border-b p-5">
              <h4 id="confirm-delete-modalLabel" className="text-base font-bold">
                {modalTitle}
              </h4>
              <button type="button" aria-label="Close" data-hs-overlay="#confirm-delete-modal">
                <i className="close-icon size-5"></i>
              </button>
            </div>
            <div className="overflow-y-auto p-5">
              <p>{getConfirmationMessage()}</p>
            </div>
            <div className="border-default-300 flex items-center justify-end gap-x-2 border-t p-4">
              <button type="button" className="btn bg-light hover:text-primary" data-hs-overlay="#confirm-delete-modal">
                {cancelButtonText}
              </button>
              <button type="button" className="btn bg-danger text-white hover:bg-danger-hover" onClick={onConfirm}>
                {confirmButtonText}
              </button>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default DeleteConfirmationModal
