import React from 'react';
import { LucideIcon } from 'lucide-react';

export interface NavItemProps {
  label: string;
  path: string;
  icon: LucideIcon;
  isActive: boolean;
  isComingSoon?: boolean;
  onNavigate: (path: string) => void;
}

export const NavItem: React.FC<NavItemProps> = ({
  label,
  path,
  icon: Icon,
  isActive,
  isComingSoon = false,
  onNavigate,
}) => {
  return (
    <button
      onClick={() => onNavigate(path)}
      className={`w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 ${
        isActive
          ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30'
          : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 border border-transparent'
      }`}
    >
      <div className="flex items-center gap-3">
        <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-indigo-400' : 'text-slate-400'}`} />
        <span>{label}</span>
      </div>

      {isComingSoon && (
        <span className="text-[10px] font-semibold tracking-wide uppercase px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700/60">
          Soon
        </span>
      )}
    </button>
  );
};
