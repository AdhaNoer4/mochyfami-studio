import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { ideaService, IdeaFormData } from '../../services/ideaService';
import { categoryService } from '../../services/categoryService';
import { ContentCategory } from '../../types';
import { ArrowLeft, Save, Sparkles, AlertCircle } from 'lucide-react';

interface IdeaFormPageProps {
  ideaId?: number;
  onNavigate: (path: string) => void;
}

export const IdeaFormPage: React.FC<IdeaFormPageProps> = ({ ideaId, onNavigate }) => {
  const isEditing = Boolean(ideaId);

  const [categories, setCategories] = useState<ContentCategory[]>([]);
  const [formData, setFormData] = useState<IdeaFormData>({
    title: '',
    slug: '',
    category_id: 0,
    hook: '',
    concept: '',
    format: 'educational',
    status: 'idea',
    priority: 1,
    notes: '',
    source_idea: '',
  });

  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Load active categories for dropdown
  useEffect(() => {
    categoryService
      .getCategories(1, '', 100)
      .then((data) => {
        setCategories(data.items);
        if (!isEditing && data.items.length > 0) {
          setFormData((prev) => ({ ...prev, category_id: data.items[0].id }));
        }
      })
      .finally(() => {
        if (!isEditing) setLoading(false);
      });
  }, [isEditing]);

  // Load existing idea when editing
  useEffect(() => {
    if (isEditing && ideaId) {
      setLoading(true);
      ideaService
        .getIdea(ideaId)
        .then((idea) => {
          setFormData({
            title: idea.title,
            slug: idea.slug,
            category_id: idea.category_id,
            hook: idea.hook || '',
            concept: idea.concept || '',
            format: idea.format,
            status: idea.status,
            priority: idea.priority,
            notes: idea.notes || '',
            source_idea: idea.source_idea || '',
          });
        })
        .catch(() => setGeneralError('Failed to fetch idea details.'))
        .finally(() => setLoading(false));
    }
  }, [ideaId, isEditing]);

  const slugify = (text: string) => {
    return text
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  };

  const handleTitleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setFormData((prev) => ({
      ...prev,
      title: val,
      slug: !isEditing || !prev.slug ? slugify(val) : prev.slug,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setGeneralError(null);

    try {
      if (isEditing && ideaId) {
        await ideaService.updateIdea(ideaId, formData);
      } else {
        await ideaService.createIdea(formData);
      }
      onNavigate('/ideas');
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        setGeneralError(err.response?.data?.message || 'Failed to save content idea.');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      <PageHeader
        title={isEditing ? 'Edit Content Idea' : 'Create Content Idea'}
        description={
          isEditing
            ? 'Update topic concept, hook, format, and priority.'
            : 'Add a new topic idea for MochyFami YouTube Shorts.'
        }
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<ArrowLeft className="w-4 h-4" />}
            onClick={() => onNavigate('/ideas')}
          >
            Back to Ideas
          </Button>
        }
      />

      {generalError && (
        <Card variant="subtle" className="border-rose-500/30 bg-rose-500/10 p-4">
          <div className="flex items-center gap-3 text-rose-300 text-xs">
            <AlertCircle className="w-5 h-5 text-rose-400 shrink-0" />
            <span>{generalError}</span>
          </div>
        </Card>
      )}

      <Card variant="default">
        <form onSubmit={handleSubmit}>
          <CardHeader>
            <CardTitle>{isEditing ? 'Idea Configuration' : 'Idea Information'}</CardTitle>
            <CardDescription>Fill out the content idea parameters below.</CardDescription>
          </CardHeader>

          <CardContent className="space-y-6">
            {loading ? (
              <div className="py-12 text-center text-xs text-slate-400 animate-pulse">
                Loading idea details...
              </div>
            ) : (
              <>
                {/* Title & Category Row */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="md:col-span-2">
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Idea Title <span className="text-rose-400">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      maxLength={255}
                      value={formData.title}
                      onChange={handleTitleChange}
                      placeholder="e.g. Why Do Cats Blink Slowly At Humans?"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                    />
                    {errors.title && <p className="text-[11px] text-rose-400 mt-1">{errors.title[0]}</p>}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Category <span className="text-rose-400">*</span>
                    </label>
                    <select
                      required
                      value={formData.category_id}
                      onChange={(e) => setFormData({ ...formData, category_id: parseInt(e.target.value, 10) })}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    >
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                    {errors.category_id && (
                      <p className="text-[11px] text-rose-400 mt-1">{errors.category_id[0]}</p>
                    )}
                  </div>
                </div>

                {/* Slug */}
                <div>
                  <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                    URL Slug <span className="text-rose-400">*</span>
                  </label>
                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      required
                      value={formData.slug}
                      onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                      placeholder="e.g. why-do-cats-blink-slowly"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      icon={<Sparkles className="w-3.5 h-3.5 text-indigo-400" />}
                      onClick={() => setFormData((prev) => ({ ...prev, slug: slugify(prev.title) }))}
                    >
                      Generate
                    </Button>
                  </div>
                  {errors.slug && <p className="text-[11px] text-rose-400 mt-1">{errors.slug[0]}</p>}
                </div>

                {/* Hook & Concept */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Opening Hook <span className="text-slate-500 font-normal">(Attention Grabber)</span>
                    </label>
                    <textarea
                      rows={3}
                      value={formData.hook || ''}
                      onChange={(e) => setFormData({ ...formData, hook: e.target.value })}
                      placeholder="e.g. If your cat slow blinks at you, it means something incredible..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 resize-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Core Concept / Angle
                    </label>
                    <textarea
                      rows={3}
                      value={formData.concept || ''}
                      onChange={(e) => setFormData({ ...formData, concept: e.target.value })}
                      placeholder="Briefly explain the scientific or entertaining explanation..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 resize-none"
                    />
                  </div>
                </div>

                {/* Format, Status, Priority Row */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Content Format <span className="text-rose-400">*</span>
                    </label>
                    <select
                      value={formData.format}
                      onChange={(e) => setFormData({ ...formData, format: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    >
                      <option value="educational">Educational</option>
                      <option value="funny_fact">Funny Fact</option>
                      <option value="storytelling">Storytelling</option>
                      <option value="comparison">Comparison</option>
                      <option value="pov">POV (Point of View)</option>
                      <option value="list">Listicle / Top Facts</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Status <span className="text-rose-400">*</span>
                    </label>
                    <select
                      value={formData.status}
                      onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    >
                      <option value="idea">Idea Backlog</option>
                      <option value="selected">Selected for Scripting</option>
                      <option value="converted">Converted to Project</option>
                      <option value="archived">Archived</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">Priority</label>
                    <div className="grid grid-cols-3 gap-1.5">
                      {[
                        { level: 1, label: 'Low' },
                        { level: 2, label: 'Med' },
                        { level: 3, label: 'High' },
                      ].map((p) => (
                        <button
                          key={p.level}
                          type="button"
                          onClick={() => setFormData({ ...formData, priority: p.level })}
                          className={`py-1.5 px-2 rounded-lg text-xs font-medium border transition-colors ${
                            formData.priority === p.level
                              ? 'bg-indigo-600/20 text-indigo-400 border-indigo-500'
                              : 'bg-slate-950 text-slate-400 border-slate-800 hover:bg-slate-800'
                          }`}
                        >
                          {p.label}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>

                {/* Notes & Source Idea */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Source Idea / Reference <span className="text-slate-500 font-normal">(Optional)</span>
                    </label>
                    <input
                      type="text"
                      value={formData.source_idea || ''}
                      onChange={(e) => setFormData({ ...formData, source_idea: e.target.value })}
                      placeholder="e.g. YouTube shorts competitor / Reddit r/cats"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Notes / Research Reminders
                    </label>
                    <input
                      type="text"
                      value={formData.notes || ''}
                      onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                      placeholder="e.g. Use cat meow sound effect in intro"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                </div>
              </>
            )}
          </CardContent>

          <CardFooter className="flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onNavigate('/ideas')}
              disabled={saving}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              size="sm"
              isLoading={saving}
              icon={<Save className="w-4 h-4" />}
            >
              {isEditing ? 'Update Idea' : 'Save Content Idea'}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  );
};
