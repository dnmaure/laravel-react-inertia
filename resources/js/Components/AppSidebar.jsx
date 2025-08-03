import { Link } from '@inertiajs/react'
import { 
  Sidebar, 
  SidebarContent, 
  SidebarFooter, 
  SidebarGroup, 
  SidebarGroupContent, 
  SidebarGroupLabel, 
  SidebarHeader, 
  SidebarInset, 
  SidebarMenu, 
  SidebarMenuButton, 
  SidebarMenuItem, 
  SidebarProvider, 
  SidebarTrigger 
} from '@/Components/ui/ui/sidebar'
import { Button } from '@/Components/ui/ui/button'
import { Separator } from '@/Components/ui/ui/separator'
import { 
  Calendar, 
  Home, 
  Settings, 
  Users, 
  Package, 
  FolderOpen,
  LogOut,
  User
} from 'lucide-react'
import ApplicationLogo from '@/Components/ApplicationLogo'

export function AppSidebar() {
  return (
    <Sidebar>
      <SidebarHeader className="border-b px-6 py-4">
        <Link href="/" className="flex items-center gap-2">
          <ApplicationLogo className="block h-8 w-auto fill-current text-gray-800" />
          <span className="font-semibold">Laravel React</span>
        </Link>
      </SidebarHeader>
      <SidebarContent className="px-3 py-2">
        <SidebarGroup>
          <SidebarGroupLabel>Main</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton asChild>
                  <Link href={route('dashboard')} className="flex items-center gap-2">
                    <Home className="h-4 w-4" />
                    Dashboard
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>

            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>





        <Separator className="my-2" />


        
      </SidebarContent>
      <SidebarFooter className="border-t px-6 py-4">
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton asChild>
                  <Link href={route('profile.edit')} className="flex items-center gap-2">
                    <User className="h-4 w-4" />
                    Profile
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
              <SidebarMenuItem>
                <SidebarMenuButton asChild>
                  <Link href={route('logout')} method="post" as="button" className="flex items-center gap-2">
                    <LogOut className="h-4 w-4" />
                    Log Out
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarFooter>
    </Sidebar>
  )
} 