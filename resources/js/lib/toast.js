import { toast } from '@/Components/ui/toast'

export const showToast = {
  success: (message, options = {}) => {
    toast.success(message, {
      duration: 4000,
      ...options,
    })
  },
  
  error: (message, options = {}) => {
    toast.error(message, {
      duration: 6000,
      ...options,
    })
  },
  
  warning: (message, options = {}) => {
    toast.warning(message, {
      duration: 5000,
      ...options,
    })
  },
  
  info: (message, options = {}) => {
    toast.info(message, {
      duration: 4000,
      ...options,
    })
  },
  
  loading: (message, options = {}) => {
    return toast.loading(message, {
      duration: Infinity,
      ...options,
    })
  },
  
  dismiss: (toastId) => {
    toast.dismiss(toastId)
  }
}

export { toast } 