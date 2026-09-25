import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../../components/ui/Card';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { projectService } from '../../services/projectService';
import { ResearchPanel } from '../../components/projects/ResearchPanel';
import { ContentProject, ContentProjectStatus, ProjectTransition } from '../../types';
import {
  ArrowLeft,
  Edit2,
  Trash2,
  Lightbulb,
  Clock,
  Globe,
  Mic,
  Quote,
  AlertCircle,
  AlertTriangle,
  CheckCircle2,
  Circle,
  FileText,
  Search,
  FolderOpen,
  Music,
  Video,
  Eye,
  Send,
} from 'lucide-react';

interface ProjectDetailPageProps {
  projectId: number;
  onNavigate: (path: string) => void;
}

export const ProjectDetailPage: React.FC<ProjectDetailPageProps> = ({ projectId, onNavigate }) => {
  const [project, setProject] = useState<ContentProject | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Delete Modal state
  const [deleteModalOpen, setDeleteModalOpen] = useState<boolean>(false);
  const [deleting, setDeleting] = useState<boolean>(false);

  // Status transition state
  const [transitioning, setTransitioning] = useState<ContentProjectStatus | null>(null);
  const [transitionError, setTransitionError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [confirmTransition, setConfirmTransition] = useState<ProjectTransition | null>(null);

  const [activeTab, setActiveTab] = useState<'overview' | 'research'>('overview');

  useEffect(() => {
    setLoading(true);
    setError(null);
    projectService
      .getProject(projectId)
      .then((data) => setProject(data))
      .catch((err) => {
        console.error('Failed to fetch project detail:', err);
        setError('Unable to load project details. It may have been deleted or does not exist.');
      })
      .finally(() => setLoading(false));
  }, [projectId]);

  const handleDelete = async () => {
    if (!project) return;
    setDeleting(true);
    try {
      await projectService.deleteProject(project.id);
      onNavigate('/projects');
    } catch (err) {
      console.error('Failed to delete project:', err);
    } finally {
      setDeleting(false);
    }
  };

  const handleTransition = async (target: ContentProjectStatus) => {
    if (!project || transitioning) return;
    setTransitioning(target);
    setTransitionError(null);
    setSuccessMessage(null);
    try {
      const updated = await projectService.updateProjectStatus(project.id, target);
      setProject(updated);
      setSuccessMessage('Project status updated successfully.');
    } catch (err) {
      const message =
        (err as { message?: string } | null)?.message ?? 'Unable to update project status.';
      setTransitionError(message);
    } finally {
      setTransitioning(null);
      setConfirmTransition(null);
    }
  };

  const getStatusBadgeVariant = (status: string) => {
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

  // Pipeline steps definition for placeholder workflow
  const pipelineSteps = [
    { key: 'research', label: 'Research', icon: Search, desc: 'Topic analysis & facts' },
    { key: 'script', label: 'Script', icon: FileText, desc: 'Voiceover & hook script' },
    { key: 'visual_plan', label: 'Visual Plan', icon: Lightbulb, desc: 'Storyboard & scenes' },
    { key: 'assets', label: 'Assets', icon: FolderOpen, desc: 'Images & footage clips' },
    { key: 'audio', label: 'Audio', icon: Music, desc: 'TTS / Voice & BGM' },
    { key: 'video', label: 'Video', icon: Video, desc: 'Rendering & assembly' },
    { key: 'review', label: 'Review', icon: Eye, desc: 'Quality assurance' },
    { key: 'publish', label: 'Publish', icon: Send, desc: 'Export & Upload' },
  ];

  // Helper to determine step completion status based on project status
  const getStepStatusClass = (stepKey: string, projectStatus: string) => {
    // Basic progression logic mapping project status to active pipeline step index
    const statusOrder: Record<string, number> = {
      draft: 0,
      researching: 0,
      research_review: 0,
      scripting: 1,
      script_review: 1,
      asset_collection: 3,
      production: 5,
      video_review: 6,
      revision: 6,
      approved: 7,
      published: 8,
      failed: 0,
      archived: 0,
    };

    const stepIndexMap: Record<string, number> = {
      research: 0,
      script: 1,
      visual_plan: 2,
      assets: 3,
      audio: 4,
      video: 5,
      review: 6,
      publish: 7,
    };

    const currentActiveIndex = statusOrder[projectStatus] ?? 0;
    const stepIdx = stepIndexMap[stepKey];

    if (stepIdx < currentActiveIndex) {
      return 'completed';
    } else if (stepIdx === currentActiveIndex) {
      return 'current';
    }
    return 'upcoming';
  };

  if (loading) {
    return (
      <div className="space-y-6 max-w-5xl mx-auto">
        <div className="h-10 bg-slate-900 rounded-lg w-1/3 animate-pulse" />
        <div className="h-64 bg-slate-900 rounded-xl animate-pulse" />
      </div>
    );
  }

  if (error || !project) {
    return (
      <div className="space-y-6 max-w-5xl mx-auto">
        <PageHeader
          title="Project Not Found"
          action={
            <Button
              variant="outline"
              size="sm"
              icon={<ArrowLeft className="w-4 h-4" />}
              onClick={() => onNavigate('/projects')}
            >
              Back to Projects
            </Button>
          }
        />
        <Card variant="subtle" className="border-rose-500/30 bg-rose-500/10 p-8 text-center">
          <div className="flex flex-col items-center justify-center gap-3">
            <AlertCircle className="w-10 h-10 text-rose-400" />
            <p className="text-sm text-rose-300">{error || 'Project standard lookup failed.'}</p>
            <Button variant="outline" size="sm" onClick={() => onNavigate('/projects')}>
              Return to Project List
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      {/* Header */}
      <PageHeader
        title={project.title}
        badge={<Badge variant={getStatusBadgeVariant(project.status)}>{project.status_label}</Badge>}
        action={
          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="sm"
              icon={<ArrowLeft className="w-4 h-4" />}
              onClick={() => onNavigate('/projects')}
            >
              Back
            </Button>
            <Button
              variant="secondary"
              size="sm"
              icon={<Edit2 className="w-4 h-4 text-indigo-400" />}
              onClick={() => onNavigate(`/projects/${project.id}/edit`)}
            >
              Edit Project
            </Button>
            <Button
              variant="danger"
              size="sm"
              icon={<Trash2 className="w-4 h-4" />}
              onClick={() => setDeleteModalOpen(true)}
            >
              Delete
            </Button>
          </div>
        }
      />

      {/* Tab Navigation */}
      <div className="flex items-center gap-1 border-b border-slate-800/80">
        <button
          className={`px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 transition-colors ${
            activeTab === 'overview'
              ? 'border-indigo-500 text-white bg-slate-800/40'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          }`}
          onClick={() => setActiveTab('overview')}
        >
          Overview
        </button>
        <button
          className={`px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 transition-colors ${
            activeTab === 'research'
              ? 'border-indigo-500 text-white bg-slate-800/40'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          }`}
          onClick={() => setActiveTab('research')}
        >
          Research
        </button>
      </div>

      {activeTab === 'research' ? (
        <ResearchPanel projectId={project.id} />
      ) : (
        <>
      {/* Overview Metadata Card */}
      <Card variant="default">
        <CardContent className="pt-6 space-y-6">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-6 border-b border-slate-800/80">
            <div>
              <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Category</span>
              <span className="text-xs font-semibold text-slate-200 flex items-center gap-1.5 mt-1">
                {project.category ? (
                  <>
                    <span
                      className="w-2 h-2 rounded-full inline-block"
                      style={{ backgroundColor: project.category.color || '#6366f1' }}
                    />
                    {project.category.name}
                  </>
                ) : (
                  'Uncategorized'
                )}
              </span>
            </div>

            <div>
              <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Duration</span>
              <span className="text-xs font-semibold text-slate-200 flex items-center gap-1.5 mt-1">
                <Clock className="w-3.5 h-3.5 text-indigo-400" />
                {project.target_duration_seconds ?? 60} seconds
              </span>
            </div>

            <div>
              <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Language</span>
              <span className="text-xs font-semibold text-slate-200 flex items-center gap-1.5 mt-1">
                <Globe className="w-3.5 h-3.5 text-indigo-400" />
                {project.language?.toUpperCase() || 'ID'}
              </span>
            </div>

            <div>
              <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Tone</span>
              <span className="text-xs font-semibold text-slate-200 flex items-center gap-1.5 mt-1">
                <Mic className="w-3.5 h-3.5 text-indigo-400" />
                {project.tone || 'Informative'}
              </span>
            </div>
          </div>

          {/* Progress Bar & Current Step */}
          <div className="space-y-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-slate-400">
                Current Step: <strong className="text-indigo-300 font-semibold">{project.current_step || project.status_label}</strong>
              </span>
              <span className="text-slate-200 font-semibold">{project.progress_percent ?? 0}% Complete</span>
            </div>
            <div className="w-full h-2.5 bg-slate-900 border border-slate-800 rounded-full overflow-hidden">
              <div
                className="h-full bg-indigo-500 rounded-full transition-all duration-300"
                style={{ width: `${Math.min(100, Math.max(0, project.progress_percent ?? 0))}%` }}
              />
            </div>
          </div>

          {/* Linked Idea Section */}
          {project.idea && (
            <div className="p-4 bg-indigo-950/30 border border-indigo-900/40 rounded-xl space-y-2">
              <div className="flex items-center gap-2 text-xs font-bold text-indigo-300">
                <Lightbulb className="w-4 h-4 text-indigo-400" />
                Original Idea
              </div>
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                  <p className="text-xs text-slate-200 font-semibold">{project.idea.title}</p>
                  {project.idea.format_label && (
                    <p className="text-[11px] text-indigo-300/80">Format: {project.idea.format_label}</p>
                  )}
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  icon={<Lightbulb className="w-3.5 h-3.5 text-indigo-400" />}
                  onClick={() => onNavigate(`/ideas/edit/${project.idea?.id}`)}
                  className="text-xs text-indigo-300 hover:text-white shrink-0"
                >
                  View Idea
                </Button>
              </div>
            </div>
          )}

          {/* Hook & Notes */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {project.hook && (
              <div className="p-4 bg-slate-950 border border-slate-800 rounded-xl space-y-2">
                <span className="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5">
                  <Quote className="w-3.5 h-3.5 text-indigo-400" /> Opening Hook Script
                </span>
                <p className="text-xs text-slate-300 italic leading-relaxed">"{project.hook}"</p>
              </div>
            )}

            {project.description && (
              <div className="p-4 bg-slate-950 border border-slate-800 rounded-xl space-y-2">
                <span className="text-[11px] font-semibold text-slate-400 block">Production Notes</span>
                <p className="text-xs text-slate-300 leading-relaxed">{project.description}</p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* STATUS WORKFLOW */}
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-white">Status Workflow</CardTitle>
              <CardDescription>
                Controlled status transitions. The backend validates every move.
              </CardDescription>
            </div>
            <Badge variant={getStatusBadgeVariant(project.status)}>{project.status_label}</Badge>
          </div>
        </CardHeader>

        <CardContent className="space-y-4">
          <div className="flex items-center gap-2 text-xs text-slate-400">
            <span className="font-semibold uppercase tracking-wider">Current Status</span>
            <span className="text-slate-600">•</span>
            <span className="font-semibold text-slate-200">{project.status_label}</span>
          </div>

          {successMessage && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-300 text-xs">
              <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{successMessage}</span>
            </div>
          )}

          {transitionError && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{transitionError}</span>
            </div>
          )}

          <div>
            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
              Available Actions
            </span>

            {project.allowed_transitions && project.allowed_transitions.length > 0 ? (
              <>
                {project.status === 'revision' && (
                  <p className="text-xs text-amber-300/90 mb-2">
                    Return this project to which stage?
                  </p>
                )}
                <div className="flex flex-wrap items-center gap-2">
                  {project.allowed_transitions.map((transition) => (
                    <Button
                      key={transition.status}
                      variant={transition.destructive ? 'danger' : 'secondary'}
                      size="sm"
                      isLoading={transitioning === transition.status}
                      disabled={transitioning !== null}
                      onClick={() => {
                        if (transition.destructive) {
                          setConfirmTransition(transition);
                        } else {
                          handleTransition(transition.status);
                        }
                      }}
                    >
                      {transition.action}
                    </Button>
                  ))}
                </div>
              </>
            ) : (
              <p className="text-xs text-slate-500">
                No transitions are available from the {project.status_label.toLowerCase()} status.
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      {/* PRODUCTION WORKFLOW PIPELINE PLACEHOLDER */}
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-white">Production Step Pipeline</CardTitle>
              <CardDescription>
                Visual workflow progression from topic research to final video publishing.
              </CardDescription>
            </div>
            <Badge variant="indigo" size="sm">
              Workflow Diagram
            </Badge>
          </div>
        </CardHeader>

        <CardContent className="space-y-6">
          <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 relative">
            {pipelineSteps.map((step) => {
              const statusClass = getStepStatusClass(step.key, project.status);
              const StepIcon = step.icon;

              let borderBg = 'bg-slate-950 border-slate-800 text-slate-500';
              let iconColor = 'text-slate-500';

              if (statusClass === 'completed') {
                borderBg = 'bg-emerald-950/20 border-emerald-800/60 text-emerald-300';
                iconColor = 'text-emerald-400';
              } else if (statusClass === 'current') {
                borderBg = 'bg-indigo-950/40 border-indigo-500 text-white shadow-lg shadow-indigo-500/10';
                iconColor = 'text-indigo-400';
              }

              return (
                <div
                  key={step.key}
                  className={`p-3 rounded-xl border flex flex-col items-center text-center transition-all ${borderBg}`}
                >
                  <div className="mb-2 flex items-center justify-center">
                    {statusClass === 'completed' ? (
                      <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                    ) : statusClass === 'current' ? (
                      <StepIcon className={`w-5 h-5 ${iconColor}`} />
                    ) : (
                      <Circle className="w-5 h-5 text-slate-700" />
                    )}
                  </div>
                  <span className="text-xs font-bold leading-tight block mb-0.5">{step.label}</span>
                  <span className="text-[10px] text-slate-400 line-clamp-1">{step.desc}</span>
                </div>
              );
            })}
          </div>

          <div className="p-3 bg-indigo-950/20 border border-indigo-900/40 rounded-lg text-xs text-indigo-300 flex items-center justify-between">
            <span>
              ℹ️ Production step execution engines (Research, Scripting, Audio TTS, Video Rendering) will be fully connected in subsequent modules.
            </span>
          </div>
        </CardContent>
      </Card>
      </>
      )}

      {/* Delete Confirmation Modal */}
      {deleteModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Content Project?</h3>
                <p className="text-xs text-slate-400">This action cannot be undone.</p>
              </div>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              Are you sure you want to delete <span className="font-bold text-white">"{project.title}"</span>?
            </p>

            <div className="flex items-center justify-end gap-3">
              <Button variant="outline" size="sm" disabled={deleting} onClick={() => setDeleteModalOpen(false)}>
                Cancel
              </Button>
              <Button variant="danger" size="sm" isLoading={deleting} onClick={handleDelete}>
                Confirm Delete
              </Button>
            </div>
          </Card>
        </div>
      )}

      {/* Status Transition Confirmation Modal */}
      {confirmTransition && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">{confirmTransition.action}</h3>
                <p className="text-xs text-slate-400">"{project.title}"</p>
              </div>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              {confirmTransition.status === 'archived'
                ? 'Are you sure you want to archive this project?'
                : 'Mark this project as failed?'}
            </p>

            <div className="flex items-center justify-end gap-3">
              <Button
                variant="outline"
                size="sm"
                disabled={transitioning !== null}
                onClick={() => setConfirmTransition(null)}
              >
                Cancel
              </Button>
              <Button
                variant="danger"
                size="sm"
                isLoading={transitioning === confirmTransition.status}
                onClick={() => handleTransition(confirmTransition.status)}
              >
                Confirm
              </Button>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
};
