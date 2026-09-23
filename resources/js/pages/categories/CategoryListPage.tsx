import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../../components/ui/Card';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { categoryService } from '../../services/categoryService';
import { ContentCategory, PaginationMeta } from '../../types';
import {
  Plus,
  Search,
  Edit2,
  Trash2,
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  XCircle,
  Tags,
} from 'lucide-react';

interface CategoryListPageProps {
  onNavigate: (path: string) => void;
}

export const CategoryListPage: React.FC<CategoryListPageProps> = ({ onNavigate }) => {
  const [categories, setCategories] = useState<ContentCategory[]>([]);
  const [pagination, setPagination] = useState<PaginationMeta>({
    total: 0,
    per_page: 10,
    current_page: 1,
    last_page: 1,
  });
  const [search, setSearch] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  // Category being deleted state
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [confirmDeleteCategory, setConfirmDeleteCategory] = useState<ContentCategory | null>(null);

  const loadCategories = async (page = 1, searchQuery = search) => {
    setLoading(true);
    setError(null);
    setDeleteError(null);
    try {
      const data = await categoryService.getCategories(page, searchQuery, 10);
      setCategories(data.items);
      setPagination(data.pagination);
    } catch (err: unknown) {
      console.error('Failed to load categories:', err);
      setError('Failed to fetch categories list from backend server.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCategories(1, search);
  }, [search]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const handleDelete = async (category: ContentCategory) => {
    setDeletingId(category.id);
    setDeleteError(null);
    try {
      await categoryService.deleteCategory(category.id);
      setConfirmDeleteCategory(null);
      // Reload current page or previous page if last item
      const targetPage =
        categories.length === 1 && pagination.current_page > 1
          ? pagination.current_page - 1
          : pagination.current_page;
      await loadCategories(targetPage);
    } catch (err: any) {
      const serverMessage = err.response?.data?.message || 'Failed to delete category.';
      setDeleteError(serverMessage);
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Content Categories"
        description="Organize YouTube Shorts content ideas and projects by topic category."
        badge={<Badge variant="indigo">{pagination.total} Categories</Badge>}
        action={
          <Button
            variant="primary"
            size="sm"
            icon={<Plus className="w-4 h-4 text-white" />}
            onClick={() => onNavigate('/categories/new')}
          >
            New Category
          </Button>
        }
      />

      {/* Delete Error Alert */}
      {deleteError && (
        <Card variant="subtle" className="border-rose-500/30 bg-rose-500/10 p-4">
          <div className="flex items-center gap-3 text-rose-300">
            <AlertTriangle className="w-5 h-5 text-rose-400 shrink-0" />
            <div className="text-xs font-medium">{deleteError}</div>
          </div>
        </Card>
      )}

      {/* Main Table Card */}
      <Card variant="default">
        <CardHeader className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <CardTitle>Category Management</CardTitle>
            <CardDescription>Search, filter, and modify content categories.</CardDescription>
          </div>

          {/* Search Input */}
          <div className="relative w-full sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={search}
              onChange={handleSearchChange}
              placeholder="Search category name..."
              className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-9 pr-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </CardHeader>

        <CardContent className="p-0 overflow-x-auto">
          {loading ? (
            <div className="p-6 space-y-3">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="h-12 bg-slate-800/60 animate-pulse rounded-lg" />
              ))}
            </div>
          ) : error ? (
            <div className="p-12 text-center text-rose-400 text-xs">{error}</div>
          ) : categories.length === 0 ? (
            <div className="p-12 text-center flex flex-col items-center justify-center">
              <Tags className="w-10 h-10 text-slate-600 mb-2" />
              <h4 className="text-sm font-semibold text-slate-300">No categories found</h4>
              <p className="text-xs text-slate-500 mt-1 max-w-xs">
                {search ? `No category matching "${search}"` : 'Create your first category to get started.'}
              </p>
            </div>
          ) : (
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-slate-800/80 bg-slate-900/50 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                  <th className="py-3 px-6">Name</th>
                  <th className="py-3 px-4">Slug</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-center">Ideas</th>
                  <th className="py-3 px-4 text-center">Projects</th>
                  <th className="py-3 px-6 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 text-xs">
                {categories.map((cat) => (
                  <tr key={cat.id} className="hover:bg-slate-800/30 transition-colors">
                    <td className="py-4 px-6 font-medium text-slate-100">
                      <div className="flex items-center gap-3">
                        <div
                          className="w-3 h-3 rounded-full shrink-0 border border-white/20"
                          style={{ backgroundColor: cat.color || '#6366f1' }}
                        />
                        <div>
                          <span>{cat.name}</span>
                          {cat.description && (
                            <p className="text-[11px] text-slate-400 font-normal truncate max-w-xs">
                              {cat.description}
                            </p>
                          )}
                        </div>
                      </div>
                    </td>

                    <td className="py-4 px-4 font-mono text-slate-400 text-[11px]">{cat.slug}</td>

                    <td className="py-4 px-4">
                      {cat.is_active ? (
                        <Badge variant="emerald" size="sm">
                          <CheckCircle2 className="w-3 h-3 mr-1" /> Active
                        </Badge>
                      ) : (
                        <Badge variant="slate" size="sm">
                          <XCircle className="w-3 h-3 mr-1" /> Inactive
                        </Badge>
                      )}
                    </td>

                    <td className="py-4 px-4 text-center font-semibold text-slate-300">
                      {cat.ideas_count ?? 0}
                    </td>

                    <td className="py-4 px-4 text-center font-semibold text-slate-300">
                      {cat.projects_count ?? 0}
                    </td>

                    <td className="py-4 px-6 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={<Edit2 className="w-3.5 h-3.5" />}
                          onClick={() => onNavigate(`/categories/edit/${cat.id}`)}
                          className="text-slate-400 hover:text-indigo-400"
                        >
                          Edit
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={<Trash2 className="w-3.5 h-3.5" />}
                          onClick={() => setConfirmDeleteCategory(cat)}
                          className="text-slate-400 hover:text-rose-400"
                        >
                          Delete
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>

        {/* Pagination Footer */}
        {pagination.last_page > 1 && (
          <div className="p-4 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
            <div>
              Showing page <span className="font-semibold text-slate-200">{pagination.current_page}</span> of{' '}
              <span className="font-semibold text-slate-200">{pagination.last_page}</span> ({pagination.total} items)
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.current_page === 1}
                onClick={() => loadCategories(pagination.current_page - 1)}
                icon={<ChevronLeft className="w-4 h-4" />}
              >
                Previous
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.current_page === pagination.last_page}
                onClick={() => loadCategories(pagination.current_page + 1)}
                icon={<ChevronRight className="w-4 h-4" />}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Delete Confirmation Modal */}
      {confirmDeleteCategory && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Category?</h3>
                <p className="text-xs text-slate-400">This action cannot be undone.</p>
              </div>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              Are you sure you want to delete <span className="font-bold text-white">{confirmDeleteCategory.name}</span>?
            </p>

            <div className="flex items-center justify-end gap-3">
              <Button
                variant="outline"
                size="sm"
                disabled={deletingId !== null}
                onClick={() => setConfirmDeleteCategory(null)}
              >
                Cancel
              </Button>
              <Button
                variant="danger"
                size="sm"
                isLoading={deletingId === confirmDeleteCategory.id}
                onClick={() => handleDelete(confirmDeleteCategory)}
              >
                Confirm Delete
              </Button>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
};
