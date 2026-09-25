import React, { useEffect, useState } from 'react';
import { useAuth } from './providers';
import { AppLayout } from '../layouts/AppLayout';
import { LoginPage } from '../pages/LoginPage';
import { DashboardPage } from '../pages/DashboardPage';
import { IdeasListPage } from '../pages/ideas/IdeasListPage';
import { IdeaFormPage } from '../pages/ideas/IdeaFormPage';
import { IdeaImportPage } from '../pages/ideas/IdeaImportPage';
import { AssetsPage } from '../pages/AssetsPage';
import { ProductionPage } from '../pages/ProductionPage';
import { AnalyticsPage } from '../pages/AnalyticsPage';
import { SettingsPage } from '../pages/SettingsPage';
import { CategoryListPage } from '../pages/categories/CategoryListPage';
import { CategoryFormPage } from '../pages/categories/CategoryFormPage';
import { ProjectsListPage } from '../pages/projects/ProjectsListPage';
import { ProjectFormPage } from '../pages/projects/ProjectFormPage';
import { ProjectDetailPage } from '../pages/projects/ProjectDetailPage';
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
    if (currentPath === '/categories/new') {
      return <CategoryFormPage onNavigate={navigate} />;
    }
    if (currentPath.startsWith('/categories/edit/')) {
      const parts = currentPath.split('/');
      const id = parseInt(parts[parts.length - 1], 10);
      return <CategoryFormPage categoryId={id} onNavigate={navigate} />;
    }
    if (currentPath.startsWith('/categories')) {
      return <CategoryListPage onNavigate={navigate} />;
    }
    if (currentPath === '/ideas/import') {
      return <IdeaImportPage onNavigate={navigate} />;
    }
    if (currentPath === '/ideas/new') {
      return <IdeaFormPage onNavigate={navigate} />;
    }
    if (currentPath.startsWith('/ideas/edit/')) {
      const parts = currentPath.split('/');
      const id = parseInt(parts[parts.length - 1], 10);
      return <IdeaFormPage ideaId={id} onNavigate={navigate} />;
    }
    if (currentPath.startsWith('/ideas')) {
      return <IdeasListPage onNavigate={navigate} />;
    }
    if (currentPath === '/projects/new') {
      return <ProjectFormPage onNavigate={navigate} />;
    }
    if (currentPath.startsWith('/projects/') && currentPath.endsWith('/edit')) {
      const parts = currentPath.split('/');
      const id = parseInt(parts[parts.length - 2], 10);
      return <ProjectFormPage projectId={id} onNavigate={navigate} />;
    }
    if (currentPath.startsWith('/projects/')) {
      const parts = currentPath.split('/');
      const id = parseInt(parts[parts.length - 1], 10);
      if (!isNaN(id)) {
        return <ProjectDetailPage projectId={id} onNavigate={navigate} />;
      }
    }
    if (currentPath.startsWith('/projects')) {
      return <ProjectsListPage onNavigate={navigate} />;
    }
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
