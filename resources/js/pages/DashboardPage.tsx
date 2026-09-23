import React, { useEffect, useState } from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { dashboardService } from '../services/dashboardService';
import { DashboardData } from '../types';
import { useAuth } from '../app/providers';
import {
  Lightbulb,
  FolderKanban,
  CheckCircle2,
  Film,
  Plus,
  AlertTriangle,
  RefreshCw,
  Clock,
  Clapperboard,
  ArrowRight,
  Sparkles,
} from 'lucide-react';

interface DashboardPageProps {
  onNavigate?: (path: string) => void;
}

export const DashboardPage: React.FC<DashboardPageProps> = ({ onNavigate }) => {
  const { user } = useAuth();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await dashboardService.getDashboardData();
      setData(result);
    } catch (err: unknown) {
      console.error('Failed to load dashboard data:', err);
      setError('Unable to load dashboard data from backend server. Please check connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  const handleQuickAction = (path: string) => {
    if (onNavigate) {
      onNavigate(path);
    } else {
      window.history.pushState({}, '', path);
      window.dispatchEvent(new Event('popstate'));
    }
  };

  // Helper for status badge color
  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'published':
        return 'emerald';
      case 'video_review':
      case 'script_review':
      case 'research_review':
      case 'revision':
        return 'amber';
      case 'production':
      case 'asset_collection':
      case 'scripting':
        return 'indigo';
      case 'failed':
        return 'rose';
      default:
        return 'slate';
    }
  };

  return (
    <div className="space-y-8">
      {/* 1. Header / Welcome Section */}
      <PageHeader
        title={`Welcome back, ${user?.name || 'Creator'}`}
        description="MochyFami YouTube Shorts content workstation overview."
        badge={<Badge variant="indigo">Workstation Active</Badge>}
        action={
          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="sm"
              icon={<Plus className="w-4 h-4 text-amber-400" />}
              onClick={() => handleQuickAction('/ideas')}
            >
              New Idea
            </Button>
            <Button
              variant="primary"
              size="sm"
              icon={<Plus className="w-4 h-4 text-white" />}
              onClick={() => handleQuickAction('/projects')}
            >
              New Project
            </Button>
          </div>
        }
      />

      {/* ERROR STATE */}
      {error && (
        <Card variant="subtle" className="border-rose-500/30 bg-rose-500/5 p-6">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400 shrink-0">
                <AlertTriangle className="w-5 h-5" />
              </div>
              <div>
                <h4 className="text-sm font-semibold text-rose-200">Failed to sync dashboard metrics</h4>
                <p className="text-xs text-rose-300/70 mt-0.5">{error}</p>
              </div>
            </div>
            <Button
              variant="danger"
              size="sm"
              icon={<RefreshCw className="w-4 h-4" />}
              onClick={fetchDashboard}
            >
              Retry Sync
            </Button>
          </div>
        </Card>
      )}

      {/* 2. Summary Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Card 1: Total Ideas */}
        <Card variant="default" className="p-5 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-500/10 text-amber-400 border border-amber-500/20 shrink-0">
              <Lightbulb className="w-6 h-6" />
            </div>
            <div>
              {loading ? (
                <div className="h-7 w-12 bg-slate-800 animate-pulse rounded mb-1" />
              ) : (
                <p className="text-2xl font-bold text-white tracking-tight">{data?.ideas_count ?? 0}</p>
              )}
              <p className="text-xs text-slate-400 font-medium">Total Ideas</p>
            </div>
          </div>
        </Card>

        {/* Card 2: Active Projects */}
        <Card variant="default" className="p-5 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl flex items-center justify-center bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 shrink-0">
              <FolderKanban className="w-6 h-6" />
            </div>
            <div>
              {loading ? (
                <div className="h-7 w-12 bg-slate-800 animate-pulse rounded mb-1" />
              ) : (
                <p className="text-2xl font-bold text-white tracking-tight">{data?.active_projects_count ?? 0}</p>
              )}
              <p className="text-xs text-slate-400 font-medium">Active Projects</p>
            </div>
          </div>
        </Card>

        {/* Card 3: In Review */}
        <Card variant="default" className="p-5 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl flex items-center justify-center bg-violet-500/10 text-violet-400 border border-violet-500/20 shrink-0">
              <CheckCircle2 className="w-6 h-6" />
            </div>
            <div>
              {loading ? (
                <div className="h-7 w-12 bg-slate-800 animate-pulse rounded mb-1" />
              ) : (
                <p className="text-2xl font-bold text-white tracking-tight">{data?.review_count ?? 0}</p>
              )}
              <p className="text-xs text-slate-400 font-medium">In Review</p>
            </div>
          </div>
        </Card>

        {/* Card 4: Published */}
        <Card variant="default" className="p-5 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shrink-0">
              <Film className="w-6 h-6" />
            </div>
            <div>
              {loading ? (
                <div className="h-7 w-12 bg-slate-800 animate-pulse rounded mb-1" />
              ) : (
                <p className="text-2xl font-bold text-white tracking-tight">{data?.published_count ?? 0}</p>
              )}
              <p className="text-xs text-slate-400 font-medium">Published Shorts</p>
            </div>
          </div>
        </Card>
      </div>

      {/* Main Grid: Recent Projects & Production Queue */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* 3. Recent Projects Section (Spans 2 cols) */}
        <div className="lg:col-span-2 space-y-4">
          <Card variant="default">
            <CardHeader className="flex items-center justify-between">
              <div>
                <CardTitle>Recent Projects</CardTitle>
                <CardDescription>Latest YouTube Shorts projects created in the workstation.</CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="text-xs text-indigo-400 hover:text-indigo-300"
                onClick={() => handleQuickAction('/projects')}
              >
                View All <ArrowRight className="w-3.5 h-3.5 ml-1" />
              </Button>
            </CardHeader>

            <CardContent className="p-0">
              {loading ? (
                <div className="p-6 space-y-4">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="h-12 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : !data?.recent_projects || data.recent_projects.length === 0 ? (
                /* EMPTY STATE */
                <div className="p-12 text-center flex flex-col items-center justify-center">
                  <div className="w-12 h-12 rounded-full bg-slate-800/60 text-slate-400 flex items-center justify-center mb-3">
                    <FolderKanban className="w-6 h-6" />
                  </div>
                  <h4 className="text-sm font-semibold text-slate-300">No active projects yet</h4>
                  <p className="text-xs text-slate-500 max-w-xs mt-1">
                    Convert ideas from your backlog into active video production projects.
                  </p>
                  <Button
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    onClick={() => handleQuickAction('/ideas')}
                  >
                    Browse Content Ideas
                  </Button>
                </div>
              ) : (
                /* SUCCESS LIST STATE */
                <div className="divide-y divide-slate-800/60">
                  {data.recent_projects.map((project) => (
                    <div
                      key={project.id}
                      className="p-4 sm:px-6 flex items-center justify-between hover:bg-slate-800/30 transition-colors"
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <div
                          className="w-2.5 h-2.5 rounded-full shrink-0"
                          style={{ backgroundColor: project.category_color || '#6366f1' }}
                        />
                        <div className="min-w-0">
                          <p className="text-sm font-medium text-slate-100 truncate">{project.title}</p>
                          <p className="text-xs text-slate-400 truncate">
                            {project.category_name || 'General'}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center gap-4 shrink-0">
                        <Badge variant={getStatusBadgeVariant(project.status)}>
                          {project.status_label}
                        </Badge>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* 4. Production Queue & 5. Quick Actions Section */}
        <div className="space-y-6">
          {/* Production Queue */}
          <Card variant="default">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Clapperboard className="w-4 h-4 text-indigo-400" />
                <CardTitle>Production Queue</CardTitle>
              </div>
              <CardDescription>Projects currently in rendering/assembly pipeline.</CardDescription>
            </CardHeader>

            <CardContent className="p-0">
              {loading ? (
                <div className="p-6 space-y-3">
                  {[1, 2].map((i) => (
                    <div key={i} className="h-10 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : !data?.production_queue || data.production_queue.length === 0 ? (
                /* EMPTY STATE */
                <div className="p-8 text-center flex flex-col items-center justify-center">
                  <div className="w-10 h-10 rounded-full bg-slate-800/60 text-slate-400 flex items-center justify-center mb-2">
                    <Clock className="w-5 h-5" />
                  </div>
                  <p className="text-xs font-medium text-slate-400">Production queue is currently empty.</p>
                </div>
              ) : (
                /* LIST */
                <div className="divide-y divide-slate-800/60">
                  {data.production_queue.map((project) => (
                    <div key={project.id} className="p-4 flex items-center justify-between">
                      <div className="min-w-0">
                        <p className="text-xs font-semibold text-slate-200 truncate">{project.title}</p>
                        <p className="text-[11px] text-slate-400">{project.category_name}</p>
                      </div>
                      <Badge variant="violet">{project.status_label}</Badge>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Quick Actions Panel */}
          <Card variant="subtle">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Sparkles className="w-4 h-4 text-amber-400" />
                <CardTitle>Quick Actions</CardTitle>
              </div>
              <CardDescription>Common workstation tasks & creation triggers.</CardDescription>
            </CardHeader>

            <CardContent className="space-y-3">
              <Button
                variant="outline"
                size="md"
                className="w-full justify-start text-left"
                icon={<Lightbulb className="w-4 h-4 text-amber-400" />}
                onClick={() => handleQuickAction('/ideas')}
              >
                Add Content Idea
              </Button>
              <Button
                variant="outline"
                size="md"
                className="w-full justify-start text-left"
                icon={<FolderKanban className="w-4 h-4 text-indigo-400" />}
                onClick={() => handleQuickAction('/projects')}
              >
                Create New Project
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
};
