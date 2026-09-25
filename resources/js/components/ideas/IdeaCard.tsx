import React from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardFooter } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { ContentIdea } from '../../types';
import { Edit2, Trash2, Quote, Flame, BookmarkCheck, Sparkles, FolderOpen } from 'lucide-react';

export interface IdeaCardProps {
  idea: ContentIdea;
  onEdit: (idea: ContentIdea) => void;
  onDelete: (idea: ContentIdea) => void;
  onConvertToProject: (idea: ContentIdea) => void;
  onOpenProject: (idea: ContentIdea) => void;
}

export const IdeaCard: React.FC<IdeaCardProps> = ({
  idea,
  onEdit,
  onDelete,
  onConvertToProject,
  onOpenProject,
}) => {
  // Format badge variant mapping
  const getFormatBadgeVariant = (format: string) => {
    switch (format) {
      case 'educational':
        return 'indigo';
      case 'funny_fact':
        return 'amber';
      case 'storytelling':
        return 'violet';
      case 'comparison':
        return 'emerald';
      default:
        return 'slate';
    }
  };

  // Status badge variant mapping
  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'idea':
        return 'slate';
      case 'selected':
        return 'amber';
      case 'converted':
        return 'emerald';
      case 'archived':
        return 'rose';
      default:
        return 'slate';
    }
  };

  // Priority indicator helper
  const renderPriorityBadge = (priority: number) => {
    if (priority === 3) {
      return (
        <Badge variant="rose" size="sm">
          <Flame className="w-3 h-3 text-rose-400 mr-1" /> High Priority
        </Badge>
      );
    }
    if (priority === 2) {
      return (
        <Badge variant="amber" size="sm">
          <Sparkles className="w-3 h-3 text-amber-400 mr-1" /> Med Priority
        </Badge>
      );
    }
    return (
      <Badge variant="slate" size="sm">
        Low Priority
      </Badge>
    );
  };

  return (
    <Card variant="default" className="flex flex-col justify-between hover:border-slate-700 transition-colors">
      <div>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between gap-2 mb-2">
            {/* Category Badge */}
            <div className="flex items-center gap-2">
              <span
                className="w-2.5 h-2.5 rounded-full shrink-0 border border-white/20"
                style={{ backgroundColor: idea.category?.color || '#6366f1' }}
              />
              <span className="text-xs font-semibold text-slate-300">
                {idea.category?.name || 'General'}
              </span>
            </div>

            {/* Status Badge */}
            <Badge variant={getStatusBadgeVariant(idea.status)} size="sm">
              {idea.status_label}
            </Badge>
          </div>

          <CardTitle className="text-base font-bold text-white line-clamp-2 leading-snug">
            {idea.title}
          </CardTitle>
        </CardHeader>

        <CardContent className="py-2 space-y-3">
          {/* Format & Priority Badges */}
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant={getFormatBadgeVariant(idea.format)} size="sm">
              {idea.format_label}
            </Badge>
            {renderPriorityBadge(idea.priority)}
          </div>

          {/* Hook snippet */}
          {idea.hook ? (
            <div className="p-3 rounded-lg bg-slate-950/80 border border-slate-800/80 text-xs text-slate-300 flex items-start gap-2 italic">
              <Quote className="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" />
              <p className="line-clamp-2">{idea.hook}</p>
            </div>
          ) : idea.concept ? (
            <p className="text-xs text-slate-400 line-clamp-2">{idea.concept}</p>
          ) : null}
        </CardContent>
      </div>

      <CardFooter className="pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2 bg-slate-900/40">
        {idea.status === 'converted' && idea.project_id ? (
          /* Converted: open the generated project */
          <Button
            variant="secondary"
            size="sm"
            icon={<FolderOpen className="w-3.5 h-3.5 text-emerald-400" />}
            onClick={() => onOpenProject(idea)}
            className="text-xs text-emerald-300 hover:text-white"
          >
            Open Project
          </Button>
        ) : (
          /* Quick Action: Convert To Project */
          <Button
            variant="secondary"
            size="sm"
            icon={<BookmarkCheck className="w-3.5 h-3.5 text-indigo-400" />}
            onClick={() => onConvertToProject(idea)}
            className="text-xs text-indigo-300 hover:text-white"
          >
            Convert To Project
          </Button>
        )}

        {/* Edit & Delete Actions */}
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            icon={<Edit2 className="w-3.5 h-3.5" />}
            onClick={() => onEdit(idea)}
            className="text-slate-400 hover:text-indigo-400 p-1.5"
            title="Edit Idea"
          />
          <Button
            variant="ghost"
            size="sm"
            icon={<Trash2 className="w-3.5 h-3.5" />}
            onClick={() => onDelete(idea)}
            className="text-slate-400 hover:text-rose-400 p-1.5"
            title="Delete Idea"
          />
        </div>
      </CardFooter>
    </Card>
  );
};
