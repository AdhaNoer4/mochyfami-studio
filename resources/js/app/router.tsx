import React, { useEffect, useState } from 'react';
import { useAuth } from './providers';
import { AppLayout } from '../layouts/AppLayout';
import { LoginPage } from '../pages/LoginPage';
import { DashboardPage } from '../pages/DashboardPage';
import { IdeasPage } from '../pages/IdeasPage';
import { ProjectsPage } from '../pages/ProjectsPage';
import { AssetsPage } from '../pages/AssetsPage';
import { ProductionPage } from '../pages/ProductionPage';
import { AnalyticsPage } from '../pages/AnalyticsPage';
import { SettingsPage } from '../pages/SettingsPage';
import { Loader2 } from 'lucide-react';

export const AppRouter: React.FC = () => {
  const { user, loading } = useAuth();
  const [currentPath, setCurrentPath] = useState<string>(window.location.pathname || '/dashboard');

  useEffect(() => {
    const handlePopState = () => {
      setCurrentPath(window.location.pathname || '/dashboard');
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  const navigate = (path: string) => {
    window.history.pushState({}, '', path);
    setCurrentPath(path);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center text-slate-400">
        <div className="flex items-center gap-3">
          <Loader2 className="w-5 h-5 animate-spin text-indigo-400" />
          <span className="text-sm font-medium">Initializing Workstation...</span>
        </div>
      </div>
    );
  }

  // If unauthenticated or navigating to /login
  if (!user || currentPath === '/login') {
    return <LoginPage onSuccess={() => navigate('/dashboard')} />;
  }

  // Render appropriate page view inside AppLayout
  const renderPage = () => {
    if (currentPath.startsWith('/ideas')) return <IdeasPage />;
    if (currentPath.startsWith('/projects')) return <ProjectsPage />;
    if (currentPath.startsWith('/assets')) return <AssetsPage />;
    if (currentPath.startsWith('/production')) return <ProductionPage />;
    if (currentPath.startsWith('/analytics')) return <AnalyticsPage />;
    if (currentPath.startsWith('/settings')) return <SettingsPage />;
    return <DashboardPage onNavigate={navigate} />;
  };

  return (
    <AppLayout currentPath={currentPath} onNavigate={navigate}>
      {renderPage()}
    </AppLayout>
  );
};
