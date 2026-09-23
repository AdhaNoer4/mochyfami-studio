import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { categoryService, CategoryFormData } from '../../services/categoryService';
import { ArrowLeft, Save, Sparkles, AlertCircle } from 'lucide-react';

interface CategoryFormPageProps {
  categoryId?: number;
  onNavigate: (path: string) => void;
}

export const CategoryFormPage: React.FC<CategoryFormPageProps> = ({ categoryId, onNavigate }) => {
  const isEditing = Boolean(categoryId);

  const [formData, setFormData] = useState<CategoryFormData>({
    name: '',
    slug: '',
    description: '',
    color: '#6366f1',
    is_active: true,
  });

  const [loading, setLoading] = useState<boolean>(isEditing);
  const [saving, setSaving] = useState<boolean>(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  useEffect(() => {
    if (isEditing && categoryId) {
      setLoading(true);
      categoryService
        .getCategory(categoryId)
        .then((cat) => {
          setFormData({
            name: cat.name,
            slug: cat.slug,
            description: cat.description || '',
            color: cat.color || '#6366f1',
            is_active: cat.is_active,
          });
        })
        .catch(() => {
          setGeneralError('Failed to fetch category details.');
        })
        .finally(() => setLoading(false));
    }
  }, [categoryId, isEditing]);

  const slugify = (text: string) => {
    return text
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  };

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setFormData((prev) => ({
      ...prev,
      name: val,
      slug: !isEditing || !prev.slug ? slugify(val) : prev.slug,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setGeneralError(null);

    try {
      if (isEditing && categoryId) {
        await categoryService.updateCategory(categoryId, formData);
      } else {
        await categoryService.createCategory(formData);
      }
      onNavigate('/categories');
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        setGeneralError(err.response?.data?.message || 'Failed to save category.');
      }
    } finally {
      setSaving(false);
    }
  };

  const presetColors = [
    '#6366f1', // Indigo
    '#8b5cf6', // Violet
    '#ec4899', // Pink
    '#f43f5e', // Rose
    '#f59e0b', // Amber
    '#10b981', // Emerald
    '#06b6d4', // Cyan
    '#3b82f6', // Blue
  ];

  return (
    <div className="space-y-6 max-w-3xl mx-auto">
      <PageHeader
        title={isEditing ? 'Edit Category' : 'Create New Category'}
        description={
          isEditing
            ? 'Update topic category details and active status.'
            : 'Add a new topic category to organize ideas and projects.'
        }
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<ArrowLeft className="w-4 h-4" />}
            onClick={() => onNavigate('/categories')}
          >
            Back to Categories
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
            <CardTitle>{isEditing ? 'Category Configuration' : 'Category Details'}</CardTitle>
            <CardDescription>Fill out the topic category attributes below.</CardDescription>
          </CardHeader>

          <CardContent className="space-y-6">
            {loading ? (
              <div className="py-12 text-center text-xs text-slate-400 animate-pulse">
                Loading category details...
              </div>
            ) : (
              <>
                {/* Category Name */}
                <div>
                  <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                    Category Name <span className="text-rose-400">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    maxLength={100}
                    value={formData.name}
                    onChange={handleNameChange}
                    placeholder="e.g. Cat Body Language"
                    className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                  />
                  {errors.name && <p className="text-[11px] text-rose-400 mt-1">{errors.name[0]}</p>}
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
                      placeholder="e.g. cat-body-language"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      icon={<Sparkles className="w-3.5 h-3.5 text-indigo-400" />}
                      onClick={() => setFormData((prev) => ({ ...prev, slug: slugify(prev.name) }))}
                      title="Auto-generate slug from name"
                    >
                      Generate
                    </Button>
                  </div>
                  {errors.slug && <p className="text-[11px] text-rose-400 mt-1">{errors.slug[0]}</p>}
                </div>

                {/* Description */}
                <div>
                  <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                    Description <span className="text-slate-500 font-normal">(Optional)</span>
                  </label>
                  <textarea
                    rows={3}
                    value={formData.description || ''}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    placeholder="Briefly describe what kind of topics fit into this category..."
                    className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 resize-none"
                  />
                </div>

                {/* Color Selection */}
                <div>
                  <label className="block text-xs font-semibold text-slate-200 mb-1.5">
                    Badge Color Accent
                  </label>
                  <div className="flex items-center gap-3">
                    <input
                      type="color"
                      value={formData.color || '#6366f1'}
                      onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                      className="w-9 h-9 rounded bg-slate-950 border border-slate-800 cursor-pointer p-0.5"
                    />
                    <div className="flex items-center gap-1.5">
                      {presetColors.map((c) => (
                        <button
                          key={c}
                          type="button"
                          onClick={() => setFormData({ ...formData, color: c })}
                          className={`w-6 h-6 rounded-full border border-slate-700 transition-transform ${
                            formData.color === c ? 'scale-125 border-white ring-2 ring-indigo-500/50' : 'hover:scale-110'
                          }`}
                          style={{ backgroundColor: c }}
                        />
                      ))}
                    </div>
                  </div>
                </div>

                {/* Active Status Checkbox */}
                <div className="flex items-center gap-3 p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="w-4 h-4 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500"
                  />
                  <label htmlFor="is_active" className="text-xs text-slate-200 font-medium cursor-pointer select-none">
                    Category is active and selectable in workstation ideas & projects
                  </label>
                </div>
              </>
            )}
          </CardContent>

          <CardFooter className="flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onNavigate('/categories')}
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
              {isEditing ? 'Update Category' : 'Save Category'}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  );
};
