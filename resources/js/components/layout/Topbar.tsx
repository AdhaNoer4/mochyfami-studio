import React from 'react';
import { User as UserIcon, LogOut, Menu, Activity } from 'lucide-react';
import { User } from '../../types';
import { Button } from '../ui/Button';

export interface TopbarProps {
  currentPathTitle: string;
  user: User | null;
  healthOk: boolean | null;
  onLogout: () => void;
  onOpenMobileSidebar: () => void;
}

export const Topbar: React.FC<TopbarProps> = ({
  currentPathTitle,
  user,
  healthOk,
  onLogout,
  onOpenMobileSidebar,
}) => {
  return (
    <header className="h-16 bg-slate-900/60 border-b border-slate-800 px-4 sm:px-8 flex items-center justify-between shrink-0 backdrop-blur-md sticky top-0 z-10">
      {/* Left section: Mobile menu toggle + Page title */}
      <div className="flex items-center gap-3">
        <button
          onClick={onOpenMobileSidebar}
          className="p-2 -ml-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 lg:hidden focus:outline-none"
          aria-label="Open sidebar"
        >
          <Menu className="w-5 h-5" />
        </button>

        <div className="flex items-center gap-2">
          <span className="text-xs font-bold uppercase tracking-wider text-indigo-400 hidden sm:inline">
            MochyFami Studio
          </span>
          <span className="text-slate-600 hidden sm:inline">/</span>
          <h2 className="text-sm font-semibold text-slate-100 capitalize">
            {currentPathTitle}
          </h2>
        </div>
      </div>

      {/* Right section: System health, User profile & Logout */}
      <div className="flex items-center gap-3 sm:gap-6">
        {/* Backend API Health Status Indicator */}
        <div className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/60 text-xs font-medium">
          <Activity className="w-3.5 h-3.5 text-indigo-400" />
          <span className="text-slate-400">Backend API:</span>
          {healthOk === null && <span className="text-amber-400">Checking...</span>}
          {healthOk === true && <span className="text-emerald-400 font-semibold">Online</span>}
          {healthOk === false && <span className="text-rose-400 font-semibold">Offline</span>}
        </div>

        {/* Authenticated User info badge */}
        {user && (
          <div className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg bg-slate-800/50 border border-slate-700/50">
            <div className="w-6 h-6 rounded-full bg-indigo-600/30 text-indigo-400 border border-indigo-500/30 flex items-center justify-center text-xs font-bold">
              <UserIcon className="w-3.5 h-3.5" />
            </div>
            <span className="text-xs font-medium text-slate-200 max-w-[120px] truncate sm:max-w-none">
              {user.name}
            </span>
          </div>
        )}

        {/* Logout action */}
        <Button
          variant="ghost"
          size="sm"
          onClick={onLogout}
          icon={<LogOut className="w-4 h-4 text-slate-400 hover:text-rose-400" />}
          className="text-slate-400 hover:text-rose-400 hover:bg-rose-500/10"
        >
          <span className="hidden sm:inline">Logout</span>
        </Button>
      </div>
    </header>
  );
};
