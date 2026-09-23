import React from 'react';
import {
  LayoutDashboard,
  Lightbulb,
  FolderKanban,
  FolderOpen,
  Clapperboard,
  BarChart3,
  Settings,
  LogOut,
  Video,
  X,
} from 'lucide-react';
import { NavItem } from './NavItem';

export interface SidebarProps {
  currentPath: string;
  onNavigate: (path: string) => void;
  onLogout: () => void;
  isOpenMobile?: boolean;
  onCloseMobile?: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({
  currentPath,
  onNavigate,
  onLogout,
  isOpenMobile = false,
  onCloseMobile,
}) => {
  const navItems = [
    { label: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
    { label: 'Ideas', path: '/ideas', icon: Lightbulb },
    { label: 'Projects', path: '/projects', icon: FolderKanban },
    { label: 'Assets', path: '/assets', icon: FolderOpen },
    { label: 'Production', path: '/production', icon: Clapperboard, comingSoon: true },
    { label: 'Analytics', path: '/analytics', icon: BarChart3, comingSoon: true },
    { label: 'Settings', path: '/settings', icon: Settings },
  ];

  const handleNavigate = (path: string) => {
    onNavigate(path);
    if (onCloseMobile) {
      onCloseMobile();
    }
  };

  const sidebarContent = (
    <div className="h-full flex flex-col justify-between select-none">
      <div>
        {/* Brand Header */}
        <div className="p-6 border-b border-slate-800/80 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 bg-gradient-to-tr from-indigo-600 to-violet-500 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
              <Video className="w-5 h-5" />
            </div>
            <div>
              <h1 className="font-bold text-white tracking-tight text-base leading-tight">
                MochyFami
              </h1>
              <p className="text-[11px] text-indigo-400 font-semibold tracking-wide uppercase">
                Content Studio
              </p>
            </div>
          </div>

          {/* Mobile close button */}
          {onCloseMobile && (
            <button
              onClick={onCloseMobile}
              className="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 lg:hidden"
            >
              <X className="w-5 h-5" />
            </button>
          )}
        </div>

        {/* Navigation Section */}
        <nav className="p-4 space-y-1.5">
          <div className="px-3 py-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Workstation
          </div>

          {navItems.map((item) => {
            const isActive =
              currentPath === item.path ||
              (item.path !== '/dashboard' && currentPath.startsWith(item.path));
            return (
              <NavItem
                key={item.path}
                label={item.label}
                path={item.path}
                icon={item.icon}
                isActive={isActive}
                isComingSoon={item.comingSoon}
                onNavigate={handleNavigate}
              />
            );
          })}
        </nav>
      </div>

      {/* Footer / Logout */}
      <div className="p-4 border-t border-slate-800/80 bg-slate-900/40">
        <button
          onClick={onLogout}
          className="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-all border border-transparent hover:border-rose-500/20"
        >
          <LogOut className="w-4 h-4 text-slate-400 group-hover:text-rose-400" />
          <span>Sign Out</span>
        </button>
      </div>
    </div>
  );

  return (
    <>
      {/* Desktop Sidebar (Fixed width) */}
      <aside className="w-64 bg-slate-900 border-r border-slate-800 hidden lg:block shrink-0">
        {sidebarContent}
      </aside>

      {/* Mobile Sidebar Overlay / Drawer */}
      {isOpenMobile && (
        <div className="fixed inset-0 z-50 lg:hidden flex">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"
            onClick={onCloseMobile}
          />

          {/* Drawer container */}
          <aside className="relative w-64 max-w-xs bg-slate-900 border-r border-slate-800 h-full z-10 flex flex-col shadow-2xl">
            {sidebarContent}
          </aside>
        </div>
      )}
    </>
  );
};
