import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { projectService, ProjectFormData } from '../../services/projectService';
import { categoryService } from '../../services/categoryService';
import { ideaService } from '../../services/ideaService';
import { ContentCategory, ContentIdea } from '../../types';
import { ArrowLeft, Save, Sparkles, AlertCircle, Lightbulb, Search, X } from 'lucide-react';

interface ProjectFormPageProps {
  projectId?: number;
  onNavigate: (path: string) => void;
}

export const ProjectFormPage: React.FC<ProjectFormPageProps> = ({ projectId, onNavigate }) => {
  const isEditing = Boolean(projectId);

  const [categories, setCategories] = useState<ContentCategory[]>([]);
  const [ideas, setIdeas] = useState<ContentIdea[]>([]);
  const [ideaSearch, setIdeaSearch] = useState<string>('');
  const [searchingIdeas, setSearchingIdeas] = useState<boolean>(false);
  const [selectedIdea, setSelectedIdea] = useState<ContentIdea | null>(null);

  const [formData, setFormData] = useState<ProjectFormData>({
    title: '',
    slug: '',
    content_idea_id: null,
    category_id: null,
    status: 'draft',
    target_duration_seconds: 60,
    language: 'id',
    tone: 'informative',
    hook: '',
    description: '',
  });

  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Load categories & initial ideas list
  useEffect(() => {
    Promise.all([
      categoryService.getCategories(1, '', 100),
      ideaService.getIdeas({ per_page: 20 }),
    ])
      .then(([catData, ideaData]) => {
        setCategories(catData.items);
        setIdeas(ideaData.items);
      })
      .catch((err) => console.error('Failed loading initial options:', err))
      .finally(() => {
        if (!isEditing) setLoading(false);
      });
  }, [isEditing]);

  // Search ideas dynamically as user types in ideaSearch
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearchingIdeas(true);
      ideaService
        .getIdeas({ search: ideaSearch, per_page: 20 })
        .then((data) => setIdeas(data.items))
        .catch((err) => console.error('Error searching ideas:', err))
        .finally(() => setSearchingIdeas(false));
    }, 300);

    return () => clearTimeout(timer);
  }, [ideaSearch]);

  // Load existing project when editing
  useEffect(() => {
    if (isEditing && projectId) {
      setLoading(true);
      projectService
        .getProject(projectId)
        .then((proj) => {
          setFormData({
            title: proj.title,
            slug: proj.slug,
            content_idea_id: proj.content_idea_id || null,
            category_id: proj.category_id || null,
            status: proj.status,
            target_duration_seconds: proj.target_duration_seconds || 60,
            language: proj.language || 'id',
            tone: proj.tone || 'informative',
            hook: proj.hook || '',
            description: proj.description || '',
          });

          if (proj.idea) {
            setSelectedIdea(proj.idea as any);
          }
        })
        .catch(() => setGeneralError('Failed to fetch project details.'))
        .finally(() => setLoading(false));
    }
  }, [projectId, isEditing]);

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

  const handleSelectIdea = (idea: ContentIdea | null) => {
    setSelectedIdea(idea);
    if (!idea) {
      setFormData((prev) => ({ ...prev, content_idea_id: null }));
      return;
    }

    setFormData((prev) => ({
      ...prev,
      content_idea_id: idea.id,
      title: prev.title || idea.title,
      slug: prev.slug || slugify(idea.title),
      category_id: prev.category_id || idea.category_id,
      hook: prev.hook || idea.hook || '',
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setGeneralError(null);

    try {
      if (isEditing && projectId) {
        await projectService.updateProject(projectId, formData);
      } else {
        await projectService.createProject(formData);
      }
      onNavigate('/projects');
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        setGeneralError(err.response?.data?.message || 'Failed to save project.');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      <PageHeader
        title={isEditing ? 'Edit Content Project' : 'Create Content Project'}
        description={
          isEditing
            ? 'Update project details, production settings, and linked concept idea.'
            : 'Initialize a new video production container.'
        }
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
            <CardTitle>{isEditing ? 'Project Configuration' : 'Project Parameters'}</CardTitle>
            <CardDescription>Fill out video production setup specifications.</CardDescription>
          </CardHeader>

          <CardContent className="space-y-6">
            {loading ? (
              <div className="py-12 text-center text-xs text-slate-400 animate-pulse">
                Loading project details...
              </div>
            ) : (
              <>
                {/* Searchable Content Idea Selector */}
                <div className="p-4 bg-slate-950/60 border border-slate-800 rounded-xl space-y-3">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-semibold text-slate-200 flex items-center gap-2">
                      <Lightbulb className="w-4 h-4 text-indigo-400" />
                      Link Content Idea <span className="text-slate-500 font-normal">(Optional)</span>
                    </label>
                    {selectedIdea && (
                      <button
                        type="button"
                        onClick={() => handleSelectIdea(null)}
                        className="text-xs text-rose-400 hover:text-rose-300 flex items-center gap-1"
                      >
                        <X className="w-3.5 h-3.5" /> Unlink Idea
                      </button>
                    )}
                  </div>

                  {selectedIdea ? (
                    <div className="flex items-center justify-between p-3 bg-indigo-950/30 border border-indigo-900/50 rounded-lg">
                      <div>
                        <p className="text-xs font-semibold text-indigo-200">{selectedIdea.title}</p>
                        <p className="text-[11px] text-slate-400">
                          Format: {selectedIdea.format_label || selectedIdea.format} • ID: #{selectedIdea.id}
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-xs text-slate-400 hover:text-white"
                        onClick={() => handleSelectIdea(null)}
                      >
                        Change
                      </Button>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <div className="relative">
                        <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                          type="text"
                          value={ideaSearch}
                          onChange={(e) => setIdeaSearch(e.target.value)}
                          placeholder="Search content ideas to link..."
                          className="w-full bg-slate-900 border border-slate-800 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                        />
                      </div>

                      <div className="max-h-40 overflow-y-auto border border-slate-800/80 rounded-lg divide-y divide-slate-800/60 bg-slate-900/40">
                        {searchingIdeas ? (
                          <div className="p-3 text-center text-xs text-slate-500">Searching ideas...</div>
                        ) : ideas.length === 0 ? (
                          <div className="p-3 text-center text-xs text-slate-500">No matching content ideas.</div>
                        ) : (
                          ideas.map((item) => (
                            <div
                              key={item.id}
                              onClick={() => handleSelectIdea(item)}
                              className="p-2.5 hover:bg-slate-800/60 cursor-pointer flex items-center justify-between text-xs transition-colors"
                            >
                              <span className="font-medium text-slate-200 truncate max-w-md">{item.title}</span>
                              <span className="text-[11px] text-slate-400 shrink-0">{item.format_label || item.format}</span>
                            </div>
                          ))
                        )}
                      </div>
                    </div>
                  )}
                </div>

                {/* Title & Category Row */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="md:col-span-2">
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Project Title <span className="text-rose-400">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      maxLength={255}
                      value={formData.title}
                      onChange={handleTitleChange}
                      placeholder="e.g. Why Cats Blink Slowly - Video Production"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                    />
                    {errors.title && <p className="text-[11px] text-rose-400 mt-1">{errors.title[0]}</p>}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">Category</label>
                    <select
                      value={formData.category_id || ''}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          category_id: e.target.value ? parseInt(e.target.value, 10) : null,
                        })
                      }
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    >
                      <option value="">None (Uncategorized)</option>
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
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
                      value={formData.slug || ''}
                      onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                      placeholder="e.g. why-cats-blink-slowly-video-production"
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

                {/* Status, Target Duration, Language, Tone Row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Production Status <span className="text-rose-400">*</span>
                    </label>
                    <select
                      value={formData.status}
                      onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    >
                      <option value="draft">Draft</option>
                      <option value="researching">Researching</option>
                      <option value="research_review">Research Review</option>
                      <option value="scripting">Scripting</option>
                      <option value="script_review">Script Review</option>
                      <option value="asset_collection">Asset Collection</option>
                      <option value="production">Production</option>
                      <option value="video_review">Video Review</option>
                      <option value="revision">Revision</option>
                      <option value="approved">Approved</option>
                      <option value="published">Published</option>
                      <option value="archived">Archived</option>
                      <option value="failed">Failed</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Target Duration (sec)
                    </label>
                    <input
                      type="number"
                      min={5}
                      max={600}
                      value={formData.target_duration_seconds}
                      onChange={(e) =>
                        setFormData({ ...formData, target_duration_seconds: parseInt(e.target.value, 10) || 60 })
                      }
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">Language</label>
                    <input
                      type="text"
                      value={formData.language}
                      onChange={(e) => setFormData({ ...formData, language: e.target.value })}
                      placeholder="e.g. id or en"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">Tone</label>
                    <input
                      type="text"
                      value={formData.tone}
                      onChange={(e) => setFormData({ ...formData, tone: e.target.value })}
                      placeholder="e.g. informative / funny"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                </div>

                {/* Hook & Description */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Opening Hook Script
                    </label>
                    <textarea
                      rows={3}
                      value={formData.hook || ''}
                      onChange={(e) => setFormData({ ...formData, hook: e.target.value })}
                      placeholder="e.g. Did you know when your cat slow blinks..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 resize-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                      Production Notes / Description
                    </label>
                    <textarea
                      rows={3}
                      value={formData.description || ''}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      placeholder="Brief notes for visual theme, voiceover style, or editing..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 resize-none"
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
              onClick={() => onNavigate('/projects')}
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
              {isEditing ? 'Update Project' : 'Save Content Project'}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  );
};
