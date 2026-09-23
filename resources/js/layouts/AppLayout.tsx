import React, { useEffect, useState } from 'react';
import { Sidebar } from '../components/layout/Sidebar';
import { Topbar } from '../components/layout/Topbar';
import { useAuth } from '../app/providers';
import { apiClient } from '../lib/api';

export interface AppLayoutProps {
  children: React.ReactNode;
  currentPath: string;
  onNavigate: (path: string) => void;
}

export const AppLayout: React.FC<AppLayoutProps> = ({
  children,
  currentPath,
  onNavigate,
}) => {
  const { user, logout } = useAuth();
  const [healthOk, setHealthOk] = useState<boolean | null>(null);
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);

  useEffect(() => {
    apiClient
      .get('/health')
      .then((res) => {
        if (res.data && res.data.success && res.data.data?.status === 'ok') {
          setHealthOk(true);
        } else {
          setHealthOk(false);
        }
      })
      .catch(() => setHealthOk(false));
  }, []);

  const getPageTitle = (path: string): string => {
    const cleanPath = path.replace('/', '').toLowerCase();
    if (!cleanPath || cleanPath === 'dashboard') return 'Dashboard';
    if (cleanPath === 'ideas') return 'Content Ideas';
    if (cleanPath === 'projects') return 'Projects';
    if (cleanPath === 'assets') return 'Media Assets';
    if (cleanPath === 'production') return 'Production Studio';
    if (cleanPath === 'analytics') return 'Channel Analytics';
    if (cleanPath === 'settings') return 'Studio Settings';
    return cleanPath;
  };

  return (
    <div className="flex h-screen bg-slate-950 text-slate-100 font-sans overflow-hidden">
      {/* Sidebar (Desktop & Mobile drawer) */}
      <Sidebar
        currentPath={currentPath}
        onNavigate={onNavigate}
        onLogout={logout}
        isOpenMobile={isMobileSidebarOpen}
        onCloseMobile={() => setIsMobileSidebarOpen(false)}
      />

      {/* Main Container */}
      <div className="flex-1 flex flex-col h-full min-w-0 overflow-hidden">
        {/* Topbar */}
        <Topbar
          currentPathTitle={getPageTitle(currentPath)}
          user={user}
          healthOk={healthOk}
          onLogout={logout}
          onOpenMobileSidebar={() => setIsMobileSidebarOpen(true)}
        />

        {/* Content Workspace */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-8">
          <div className="max-w-7xl mx-auto">{children}</div>
        </main>
      </div>
    </div>
  );
};
