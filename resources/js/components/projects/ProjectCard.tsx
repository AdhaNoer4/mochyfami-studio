import React from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardFooter } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { ContentProject } from '../../types';
import { Edit2, Trash2, Eye, Lightbulb } from 'lucide-react';

export interface ProjectCardProps {
  project: ContentProject;
  onNavigate: (path: string) => void;
  onEdit: (project: ContentProject) => void;
  onDelete: (project: ContentProject) => void;
}

export const ProjectCard: React.FC<ProjectCardProps> = ({
  project,
  onNavigate,
  onEdit,
  onDelete,
}) => {
  // Status badge variant mapping
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

  const progress = project.progress_percent ?? 0;

  return (
    <Card variant="default" className="flex flex-col justify-between hover:border-slate-700 transition-colors">
      <div>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between gap-2 mb-2">
            {/* Category Indicator */}
            {project.category ? (
              <div className="flex items-center gap-2">
                <span
                  className="w-2.5 h-2.5 rounded-full shrink-0 border border-white/20"
                  style={{ backgroundColor: project.category.color || '#6366f1' }}
                />
                <span className="text-xs font-semibold text-slate-300">
                  {project.category.name}
                </span>
              </div>
            ) : (
              <span className="text-xs font-semibold text-slate-500">Uncategorized</span>
            )}

            {/* Status Badge */}
            <Badge variant={getStatusBadgeVariant(project.status)} size="sm">
              {project.status_label}
            </Badge>
          </div>

          <CardTitle className="text-base font-bold text-white line-clamp-2 leading-snug">
            <button
              type="button"
              onClick={() => onNavigate(`/projects/${project.id}`)}
              className="hover:text-indigo-400 transition-colors text-left font-bold"
            >
              {project.title}
            </button>
          </CardTitle>
        </CardHeader>

        <CardContent className="py-2 space-y-3">
          {/* Linked Idea */}
          {project.idea ? (
            <div className="flex items-center gap-2 text-xs text-indigo-300 bg-indigo-950/40 border border-indigo-900/40 rounded-md px-2.5 py-1.5">
              <Lightbulb className="w-3.5 h-3.5 text-indigo-400 shrink-0" />
              <span className="truncate">Idea: {project.idea.title}</span>
            </div>
          ) : null}

          {/* Description snippet or Hook */}
          {project.description ? (
            <p className="text-xs text-slate-400 line-clamp-2">{project.description}</p>
          ) : project.hook ? (
            <p className="text-xs text-slate-400 italic line-clamp-2">"{project.hook}"</p>
          ) : null}

          {/* Progress bar */}
          <div className="space-y-1">
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span>Progress</span>
              <span className="font-semibold text-slate-200">{progress}%</span>
            </div>
            <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
              <div
                className="h-full bg-indigo-500 rounded-full transition-all duration-300"
                style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
              />
            </div>
          </div>
        </CardContent>
      </div>

      <CardFooter className="pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2 bg-slate-900/40">
        <Button
          variant="ghost"
          size="sm"
          icon={<Eye className="w-3.5 h-3.5 text-indigo-400" />}
          onClick={() => onNavigate(`/projects/${project.id}`)}
          className="text-xs text-indigo-300 hover:text-white"
        >
          View Details
        </Button>

        {/* Edit & Delete Actions */}
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            icon={<Edit2 className="w-3.5 h-3.5" />}
            onClick={() => onEdit(project)}
            className="text-slate-400 hover:text-indigo-400 p-1.5"
            title="Edit Project"
          />
          <Button
            variant="ghost"
            size="sm"
            icon={<Trash2 className="w-3.5 h-3.5" />}
            onClick={() => onDelete(project)}
            className="text-slate-400 hover:text-rose-400 p-1.5"
            title="Delete Project"
          />
        </div>
      </CardFooter>
    </Card>
  );
};
