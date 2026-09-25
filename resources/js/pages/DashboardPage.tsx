import React, { useEffect, useState } from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { dashboardService } from '../services/dashboardService';
import {
  ContentProjectStatus,
  DashboardData,
  DashboardIdeaStats,
  DashboardRecentIdea,
  DashboardProjectItem,
} from '../types';
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
  PieChart,
} from 'lucide-react';

interface DashboardPageProps {
  onNavigate?: (path: string) => void;
}

const projectStatusLabels: Record<ContentProjectStatus, string> = {
  draft: 'Draft',
  researching: 'Researching',
  research_review: 'Research Review',
  scripting: 'Scripting',
  script_review: 'Script Review',
  asset_collection: 'Asset Collection',
  production: 'Production',
  video_review: 'Video Review',
  revision: 'Revision',
  approved: 'Approved',
  published: 'Published',
  archived: 'Archived',
  failed: 'Failed',
};

const projectStatusGroups: { label: string; statuses: ContentProjectStatus[] }[] = [
  { label: 'Planning', statuses: ['draft'] },
  { label: 'Research', statuses: ['researching', 'research_review'] },
  { label: 'Script', statuses: ['scripting', 'script_review'] },
  { label: 'Assets', statuses: ['asset_collection'] },
  { label: 'Production', statuses: ['production'] },
  { label: 'Review', statuses: ['video_review', 'revision'] },
  { label: 'Publishing', statuses: ['approved', 'published'] },
  { label: 'Other', statuses: ['archived', 'failed'] },
];

const ideaStatusEntries: { key: keyof DashboardIdeaStats; label: string }[] = [
  { key: 'idea', label: 'Idea' },
  { key: 'selected', label: 'Selected' },
  { key: 'converted', label: 'Converted' },
  { key: 'archived', label: 'Archived' },
];

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
      setError('Unable to load dashboard data.');
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

  const formatRelativeTime = (iso: string) => {
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const minutes = Math.floor(diffMs / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  };

  const priorityLabel = (priority: number) => {
    if (priority >= 3) return 'High';
    if (priority === 2) return 'Medium';
    return 'Low';
  };

  const getProjectStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'draft':
      case 'archived':
        return 'slate';
      case 'researching':
      case 'research_review':
        return 'indigo';
      case 'scripting':
      case 'script_review':
        return 'violet';
      case 'asset_collection':
      case 'production':
      case 'video_review':
      case 'revision':
        return 'amber';
      case 'approved':
      case 'published':
        return 'emerald';
      case 'failed':
        return 'rose';
      default:
        return 'slate';
    }
  };

  const getIdeaStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'idea':
        return 'amber';
      case 'selected':
        return 'indigo';
      case 'converted':
        return 'emerald';
      case 'archived':
        return 'slate';
      default:
        return 'slate';
    }
  };

  const summaryCards = [
    {
      label: 'Total Ideas',
      value: data?.overview.total_ideas,
      icon: Lightbulb,
      colors: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
    },
    {
      label: 'Total Projects',
      value: data?.overview.total_projects,
      icon: FolderKanban,
      colors: 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20',
    },
    {
      label: 'Active Projects',
      value: data?.overview.active_projects,
      icon: CheckCircle2,
      colors: 'bg-violet-500/10 text-violet-400 border border-violet-500/20',
    },
    {
      label: 'Published Projects',
      value: data?.overview.published_projects,
      icon: Film,
      colors: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
    },
  ];

  const renderIdeaRow = (idea: DashboardRecentIdea) => (
    <div key={idea.id} className="p-4 sm:px-6 flex items-center justify-between hover:bg-slate-800/30 transition-colors">
      <div className="flex items-center gap-3 min-w-0">
        <div
          className="w-2.5 h-2.5 rounded-full shrink-0"
          style={{ backgroundColor: idea.category_color || '#6366f1' }}
        />
        <div className="min-w-0">
          <p className="text-sm font-medium text-slate-100 truncate">{idea.title}</p>
          <p className="text-xs text-slate-400 truncate">
            {idea.category || 'General'} · {formatRelativeTime(idea.created_at)}
          </p>
        </div>
      </div>
      <Badge variant={getIdeaStatusBadgeVariant(idea.status)} className="shrink-0">
        {idea.status_label}
      </Badge>
    </div>
  );

  const renderProjectRow = (project: DashboardProjectItem) => (
    <div key={project.id} className="p-4 sm:px-6 flex items-center justify-between hover:bg-slate-800/30 transition-colors">
      <div className="flex items-center gap-3 min-w-0">
        <div className="min-w-0">
          <p className="text-sm font-medium text-slate-100 truncate">{project.title}</p>
          <p className="text-xs text-slate-400 truncate">
            Priority: {priorityLabel(project.priority)} · {formatRelativeTime(project.updated_at)}
          </p>
        </div>
      </div>
      <Badge variant={getProjectStatusBadgeVariant(project.status)} className="shrink-0">
        {project.status_label}
      </Badge>
    </div>
  );

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
              icon={<RefreshCw className="w-4 h-4" />}
              onClick={fetchDashboard}
              disabled={loading}
            >
              Refresh
            </Button>
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
                <h4 className="text-sm font-semibold text-rose-200">Failed to load dashboard</h4>
                <p className="text-xs text-rose-300/70 mt-0.5">{error}</p>
              </div>
            </div>
            <Button
              variant="danger"
              size="sm"
              icon={<RefreshCw className="w-4 h-4" />}
              onClick={fetchDashboard}
            >
              Try Again
            </Button>
          </div>
        </Card>
      )}

      {/* 2. Summary Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {summaryCards.map((card) => {
          const Icon = card.icon;

          return (
            <Card key={card.label} variant="default" className="p-5 flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className={`w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${card.colors}`}>
                  <Icon className="w-6 h-6" />
                </div>
                <div>
                  {loading ? (
                    <div className="h-7 w-12 bg-slate-800 animate-pulse rounded mb-1" />
                  ) : (
                    <p className="text-2xl font-bold text-white tracking-tight">{card.value ?? 0}</p>
                  )}
                  <p className="text-xs text-slate-400 font-medium">{card.label}</p>
                </div>
              </div>
            </Card>
          );
        })}
      </div>

      {/* Main Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left column: Recent Ideas, Recent Projects, Project Status */}
        <div className="lg:col-span-2 space-y-6">
          {/* Recent Ideas */}
          <Card variant="default">
            <CardHeader className="flex items-center justify-between">
              <div>
                <CardTitle>
                  <span className="inline-flex items-center gap-2">
                    <Lightbulb className="w-4 h-4 text-amber-400" /> Recent Ideas
                  </span>
                </CardTitle>
                <CardDescription>Latest content ideas added to the backlog.</CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="text-xs text-indigo-400 hover:text-indigo-300"
                onClick={() => handleQuickAction('/ideas')}
              >
                View All Ideas <ArrowRight className="w-3.5 h-3.5 ml-1" />
              </Button>
            </CardHeader>

            <CardContent className="p-0">
              {loading ? (
                <div className="p-6 space-y-4">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="h-12 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : data && data.recent_ideas.length === 0 ? (
                /* EMPTY STATE */
                <div className="p-12 text-center flex flex-col items-center justify-center">
                  <div className="w-12 h-12 rounded-full bg-slate-800/60 text-slate-400 flex items-center justify-center mb-3">
                    <Lightbulb className="w-6 h-6" />
                  </div>
                  <h4 className="text-sm font-semibold text-slate-300">No content ideas yet.</h4>
                  <p className="text-xs text-slate-500 max-w-xs mt-1">
                    Build your idea backlog to power future video production.
                  </p>
                  <Button
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    onClick={() => handleQuickAction('/ideas')}
                  >
                    Create Idea
                  </Button>
                </div>
              ) : (
                /* LIST */
                <div className="divide-y divide-slate-800/60">
                  {data?.recent_ideas.map(renderIdeaRow)}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Recent Projects */}
          <Card variant="default">
            <CardHeader className="flex items-center justify-between">
              <div>
                <CardTitle>
                  <span className="inline-flex items-center gap-2">
                    <FolderKanban className="w-4 h-4 text-indigo-400" /> Recent Projects
                  </span>
                </CardTitle>
                <CardDescription>Latest projects by recent activity.</CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="text-xs text-indigo-400 hover:text-indigo-300"
                onClick={() => handleQuickAction('/projects')}
              >
                View All Projects <ArrowRight className="w-3.5 h-3.5 ml-1" />
              </Button>
            </CardHeader>

            <CardContent className="p-0">
              {loading ? (
                <div className="p-6 space-y-4">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="h-12 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : data && data.recent_projects.length === 0 ? (
                /* EMPTY STATE */
                <div className="p-12 text-center flex flex-col items-center justify-center">
                  <div className="w-12 h-12 rounded-full bg-slate-800/60 text-slate-400 flex items-center justify-center mb-3">
                    <FolderKanban className="w-6 h-6" />
                  </div>
                  <h4 className="text-sm font-semibold text-slate-300">No content projects yet.</h4>
                  <p className="text-xs text-slate-500 max-w-xs mt-1">
                    Convert ideas from your backlog into active projects.
                  </p>
                  <Button
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    onClick={() => handleQuickAction('/projects')}
                  >
                    Create Project
                  </Button>
                </div>
              ) : (
                /* LIST */
                <div className="divide-y divide-slate-800/60">
                  {data?.recent_projects.map(renderProjectRow)}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Project Status Distribution */}
          <Card variant="default">
            <CardHeader>
              <CardTitle>
                <span className="inline-flex items-center gap-2">
                  <PieChart className="w-4 h-4 text-violet-400" /> Project Status Distribution
                </span>
              </CardTitle>
              <CardDescription>Projects grouped by production stage.</CardDescription>
            </CardHeader>

            <CardContent>
              {loading ? (
                <div className="space-y-4">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="h-10 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                  {projectStatusGroups.map((group) => (
                    <div key={group.label}>
                      <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        {group.label}
                      </p>
                      <div className="space-y-1">
                        {group.statuses.map((status) => (
                          <div
                            key={status}
                            className="flex items-center justify-between text-xs py-0.5"
                          >
                            <span className="text-slate-300">{projectStatusLabels[status]}</span>
                            <span className="font-semibold text-slate-100">
                              {data?.projects.by_status[status] ?? 0}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Right column: Idea Status, Production Queue, Quick Actions */}
        <div className="space-y-6">
          {/* Idea Status Distribution */}
          <Card variant="default">
            <CardHeader>
              <CardTitle>
                <span className="inline-flex items-center gap-2">
                  <Lightbulb className="w-4 h-4 text-amber-400" /> Idea Status Distribution
                </span>
              </CardTitle>
              <CardDescription>Backlog ideas by stage.</CardDescription>
            </CardHeader>

            <CardContent className="space-y-3">
              {loading ? (
                <div className="space-y-3">
                  {[1, 2, 3, 4].map((i) => (
                    <div key={i} className="h-8 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : (
                ideaStatusEntries.map((entry) => {
                  const count = data?.ideas[entry.key] ?? 0;
                  const total = data?.ideas.total ?? 0;
                  const percent = total > 0 ? Math.round((count / total) * 100) : 0;

                  return (
                    <div key={entry.key}>
                      <div className="flex items-center justify-between text-xs mb-1">
                        <span className="text-slate-300">{entry.label}</span>
                        <span className="font-semibold text-slate-100">{count}</span>
                      </div>
                      <div className="w-full h-1.5 bg-slate-900 border border-slate-800 rounded-full overflow-hidden">
                        <div
                          className="h-full bg-amber-500/70 rounded-full transition-all duration-300"
                          style={{ width: `${percent}%` }}
                        />
                      </div>
                    </div>
                  );
                })
              )}
            </CardContent>
          </Card>

          {/* Production Queue */}
          <Card variant="default">
            <CardHeader>
              <CardTitle>
                <span className="inline-flex items-center gap-2">
                  <Clapperboard className="w-4 h-4 text-indigo-400" /> Production Queue
                </span>
              </CardTitle>
              <CardDescription>Active projects waiting on work, oldest first.</CardDescription>
            </CardHeader>

            <CardContent className="p-0">
              {loading ? (
                <div className="p-6 space-y-3">
                  {[1, 2].map((i) => (
                    <div key={i} className="h-14 bg-slate-800/60 animate-pulse rounded-lg" />
                  ))}
                </div>
              ) : data && data.production_queue.length === 0 ? (
                /* EMPTY STATE */
                <div className="p-8 text-center flex flex-col items-center justify-center">
                  <div className="w-10 h-10 rounded-full bg-slate-800/60 text-slate-400 flex items-center justify-center mb-2">
                    <Clock className="w-5 h-5" />
                  </div>
                  <p className="text-xs font-medium text-slate-400">No active projects in production.</p>
                </div>
              ) : (
                /* LIST */
                <div className="divide-y divide-slate-800/60">
                  {data?.production_queue.map((project) => (
                    <button
                      key={project.id}
                      type="button"
                      className="w-full text-left p-4 flex items-center justify-between hover:bg-slate-800/30 transition-colors cursor-pointer"
                      onClick={() => handleQuickAction(`/projects/${project.id}`)}
                    >
                      <div className="min-w-0">
                        <p className="text-xs font-semibold text-slate-200 truncate">{project.title}</p>
                        <p className="text-[11px] text-slate-400 truncate">
                          Priority: {priorityLabel(project.priority)} · {formatRelativeTime(project.updated_at)}
                        </p>
                      </div>
                      <Badge variant={getProjectStatusBadgeVariant(project.status)} className="shrink-0 ml-3">
                        {project.status_label}
                      </Badge>
                    </button>
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