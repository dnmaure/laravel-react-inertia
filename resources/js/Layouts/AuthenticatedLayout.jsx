import { Head } from '@inertiajs/react'
import { AppSidebar } from '@/Components/AppSidebar'
import { PageBreadcrumb } from '@/Components/PageBreadcrumb'
import { Separator } from '@/Components/ui/ui/separator'
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from '@/Components/ui/ui/sidebar'
import { ToastProvider } from '@/Components/ui/toast'

export default function AuthenticatedLayout({ header, children, breadcrumbs = [] }) {
  return (
    <SidebarProvider>
      <Head title={header} />
      <AppSidebar />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator
            orientation="vertical"
            className="mr-2 data-[orientation=vertical]:h-4"
          />
          <PageBreadcrumb items={breadcrumbs} />
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4">
          {children}
        </div>
      </SidebarInset>
      <ToastProvider />
    </SidebarProvider>
  )
}
