import React, { useEffect, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { Badge } from '../ui/Badge';
import { ContentIdea, ConvertIdeaPayload, ConvertIdeaResponse } from '../../types';
import { ideaService } from '../../services/ideaService';
import { ApiError } from '../../types';
import { BookmarkCheck, X, AlertTriangle } from 'lucide-react';

export interface ConvertIdeaModalProps {
  idea: ContentIdea;
  onClose: () => void;
  onSuccess: (result: ConvertIdeaResponse) => void;
  onAlreadyConverted: () => void;
}

export const ConvertIdeaModal: React.FC<ConvertIdeaModalProps> = ({
  idea,
  onClose,
  onSuccess,
  onAlreadyConverted,
}) => {
  const [title, setTitle] = useState<string>(idea.title);
  const [priority, setPriority] = useState<number>(2);
  const [notes, setNotes] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setTitle(idea.title);
    setNotes('');
    setError(null);
  }, [idea]);

  const handleSubmit = async () => {
    if (loading) return;

    setLoading(true);
    setError(null);

    const payload: ConvertIdeaPayload = {
      title: title.trim() !== '' ? title.trim() : undefined,
      priority,
      notes: notes.trim() !== '' ? notes.trim() : undefined,
    };

    try {
      const result = await ideaService.convertIdeaToProject(idea.id, payload);
      onSuccess(result);
    } catch (err) {
      const apiError = err as ApiError;
      setError(apiError.message || 'Conversion failed. Please try again.');

      if (apiError.status === 409) {
        onAlreadyConverted();
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <Card variant="default" className="max-w-lg w-full p-6 border-indigo-500/30 shadow-2xl">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
              <BookmarkCheck className="w-6 h-6" />
            </div>
            <div>
              <h3 className="text-base font-bold text-white">Convert To Project</h3>
              <p className="text-xs text-indigo-400 font-medium">Create a production project from this idea</p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={loading}
            className="p-1 text-slate-400 hover:text-white rounded-lg disabled:opacity-50"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Idea summary */}
        <div className="p-4 bg-slate-950 border border-slate-800 rounded-xl mb-5 space-y-2">
          <p className="text-sm font-semibold text-white leading-snug">"{idea.title}"</p>
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant="slate" size="sm">
              {idea.category?.name || 'General'}
            </Badge>
            <Badge variant="indigo" size="sm">
              {idea.format_label}
            </Badge>
            <Badge variant="amber" size="sm">
              {idea.status_label}
            </Badge>
          </div>
        </div>

        {/* Optional fields */}
        <div className="space-y-4">
          <div>
            <label className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
              Project Title
            </label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder={idea.title}
              className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
              Priority
            </label>
            <select
              value={priority}
              onChange={(e) => setPriority(parseInt(e.target.value, 10))}
              className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
            >
              <option value={1}>Low</option>
              <option value={2}>Medium</option>
              <option value={3}>High</option>
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
              Notes
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              placeholder="Optional production notes for this project."
              className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 resize-none"
            />
          </div>
        </div>

        {/* Error */}
        {error && (
          <div className="mt-4 p-3 bg-rose-500/10 border border-rose-500/30 rounded-lg flex items-start gap-2">
            <AlertTriangle className="w-4 h-4 text-rose-400 shrink-0 mt-0.5" />
            <p className="text-xs text-rose-300 leading-relaxed">{error}</p>
          </div>
        )}

        <div className="flex items-center justify-end gap-3 mt-6">
          <Button variant="outline" size="sm" disabled={loading} onClick={onClose}>
            Cancel
          </Button>
          <Button variant="primary" size="sm" isLoading={loading} onClick={handleSubmit}>
            Create Project
          </Button>
        </div>
      </Card>
    </div>
  );
};