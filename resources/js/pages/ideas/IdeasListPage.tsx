import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card } from '../../components/ui/Card';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { IdeaCard } from '../../components/ideas/IdeaCard';
import { ConvertIdeaModal } from '../../components/ideas/ConvertIdeaModal';
import { IdeaFilterBar, DEFAULT_IDEA_FILTERS } from '../../components/ideas/IdeaFilterBar';
import { ideaService } from '../../services/ideaService';
import { categoryService } from '../../services/categoryService';
import { ContentCategory, ContentIdea, ConvertIdeaResponse, IdeaFilterParams, PaginationMeta } from '../../types';
import {
  Plus,
  Upload,
  Lightbulb,
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  BookmarkCheck,
  X,
  RefreshCw,
  FilterX,
  FolderOpen,
} from 'lucide-react';

interface IdeasListPageProps {
  onNavigate: (path: string) => void;
}

export const IdeasListPage: React.FC<IdeasListPageProps> = ({ onNavigate }) => {
  const [ideas, setIdeas] = useState<ContentIdea[]>([]);
  const [categories, setCategories] = useState<ContentCategory[]>([]);
  const [pagination, setPagination] = useState<PaginationMeta>({
    total: 0,
    per_page: 10,
    current_page: 1,
    last_page: 1,
  });

  // Parse initial filters from browser URL query string
  const getInitialFilters = (): IdeaFilterParams => {
    const params = new URLSearchParams(window.location.search);
    return {
      page: parseInt(params.get('page') || '1', 10),
      per_page: parseInt(params.get('per_page') || '10', 10),
      search: params.get('search') || '',
      category_id: params.get('category_id') || '',
      format: params.get('format') || '',
      status: params.get('status') || '',
      priority: params.get('priority') || '',
      sort: (params.get('sort') as any) || 'created_at',
      direction: (params.get('direction') as any) || 'desc',
    };
  };

  const [filters, setFilters] = useState<IdeaFilterParams>(getInitialFilters);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Convert modal & Delete modal states
  const [convertModalIdea, setConvertModalIdea] = useState<ContentIdea | null>(null);
  const [deleteModalIdea, setDeleteModalIdea] = useState<ContentIdea | null>(null);
  const [deleting, setDeleting] = useState<boolean>(false);
  const [convertedNotice, setConvertedNotice] = useState<{ projectId: number; ideaTitle: string } | null>(null);

  // Load categories for filter dropdown
  useEffect(() => {
    categoryService.getCategories(1, '', 100).then((data) => {
      setCategories(data.items);
    });
  }, []);

  // Sync state to URL search parameters
  const syncFiltersToUrl = (newFilters: IdeaFilterParams) => {
    const params = new URLSearchParams();
    if (newFilters.page && newFilters.page > 1) params.set('page', String(newFilters.page));
    if (newFilters.per_page && newFilters.per_page !== 10) params.set('per_page', String(newFilters.per_page));
    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.category_id) params.set('category_id', String(newFilters.category_id));
    if (newFilters.format) params.set('format', newFilters.format);
    if (newFilters.status) params.set('status', newFilters.status);
    if (newFilters.priority) params.set('priority', String(newFilters.priority));
    if (newFilters.sort && newFilters.sort !== 'created_at') params.set('sort', newFilters.sort);
    if (newFilters.direction && newFilters.direction !== 'desc') params.set('direction', newFilters.direction);

    const queryString = params.toString();
    const newUrl = `${window.location.pathname}${queryString ? `?${queryString}` : ''}`;
    window.history.replaceState({}, '', newUrl);
  };

  const fetchIdeas = async (currentFilters = filters) => {
    setLoading(true);
    setError(null);
    try {
      const data = await ideaService.getIdeas(currentFilters);
      setIdeas(data.items);
      setPagination(data.pagination);
    } catch (err: unknown) {
      console.error('Failed to load content ideas:', err);
      setError('Unable to load content ideas from server. Please check connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    syncFiltersToUrl(filters);
    fetchIdeas(filters);
  }, [
    filters.page,
    filters.per_page,
    filters.search,
    filters.category_id,
    filters.format,
    filters.status,
    filters.priority,
    filters.sort,
    filters.direction,
  ]);

  const handleFilterChange = (updatedFilters: IdeaFilterParams) => {
    setFilters(updatedFilters);
  };

  const handleClearFilters = () => {
    setFilters(DEFAULT_IDEA_FILTERS);
  };

  const handleDelete = async (idea: ContentIdea) => {
    setDeleting(true);
    try {
      await ideaService.deleteIdea(idea.id);
      setDeleteModalIdea(null);
      await fetchIdeas(filters);
    } catch (err: unknown) {
      console.error('Failed to delete idea:', err);
    } finally {
      setDeleting(false);
    }
  };

  const handleConvertSuccess = (result: ConvertIdeaResponse) => {
    setConvertModalIdea(null);
    setConvertedNotice({ projectId: result.project.id, ideaTitle: result.idea.title });
    fetchIdeas(filters);
  };

  const handleAlreadyConverted = () => {
    setConvertModalIdea(null);
    fetchIdeas(filters);
  };

  const handleDismissNotice = () => {
    setConvertedNotice(null);
  };

  // Check if current view is filtered away from default state
  const isFiltered = Boolean(
    (filters.search && filters.search.trim() !== '') ||
      filters.category_id ||
      filters.format ||
      filters.status ||
      filters.priority ||
      (filters.sort && filters.sort !== 'created_at') ||
      (filters.direction && filters.direction !== 'desc')
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Content Ideas"
        description="Brainstorm, curate, and filter YouTube Shorts topic ideas."
        badge={<Badge variant="amber">{pagination.total} Ideas</Badge>}
        action={
          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="sm"
              icon={<Upload className="w-4 h-4 text-indigo-400" />}
              onClick={() => onNavigate('/ideas/import')}
            >
              Import CSV
            </Button>
            <Button
              variant="primary"
              size="sm"
              icon={<Plus className="w-4 h-4 text-white" />}
              onClick={() => onNavigate('/ideas/new')}
            >
              New Idea
            </Button>
          </div>
        }
      />

      {/* Filter Toolbar */}
      <IdeaFilterBar
        categories={categories}
        filters={filters}
        onChange={handleFilterChange}
        onClear={handleClearFilters}
      />

      {/* SUCCESS NOTIFICATION */}
      {convertedNotice && (
        <Card variant="subtle" className="border-emerald-500/30 bg-emerald-500/10 p-4">
          <div className="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div className="flex items-start gap-2">
              <BookmarkCheck className="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
              <div>
                <p className="text-xs text-emerald-300">Content project created successfully.</p>
                <p className="text-[11px] text-emerald-400/70 mt-0.5">
                  "{convertedNotice.ideaTitle}" has been converted into a project.
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button
                variant="secondary"
                size="sm"
                icon={<FolderOpen className="w-3.5 h-3.5 text-emerald-400" />}
                onClick={() => onNavigate(`/projects/${convertedNotice.projectId}`)}
                className="text-xs text-emerald-300 hover:text-white"
              >
                Open Project
              </Button>
              <Button variant="ghost" size="sm" onClick={handleDismissNotice}>
                Dismiss
              </Button>
            </div>
          </div>
        </Card>
      )}

      {/* ERROR STATE */}
      {error && (
        <Card variant="subtle" className="border-rose-500/30 bg-rose-500/10 p-6 text-center">
          <div className="flex flex-col items-center justify-center gap-3">
            <AlertTriangle className="w-8 h-8 text-rose-400" />
            <p className="text-xs text-rose-300 max-w-md">{error}</p>
            <Button
              variant="danger"
              size="sm"
              icon={<RefreshCw className="w-3.5 h-3.5" />}
              onClick={() => fetchIdeas(filters)}
            >
              Try Again
            </Button>
          </div>
        </Card>
      )}

      {/* Ideas Grid Content */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div key={i} className="h-48 bg-slate-900 border border-slate-800 rounded-xl animate-pulse p-6" />
          ))}
        </div>
      ) : !error && ideas.length === 0 ? (
        isFiltered ? (
          /* FILTERED EMPTY STATE */
          <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
            <FilterX className="w-10 h-10 text-amber-400/70 mb-3" />
            <h4 className="text-sm font-semibold text-slate-200">No ideas match your current filters.</h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Try adjusting your search keyword, category, status, or priority filters to view results.
            </p>
            <Button
              variant="outline"
              size="sm"
              className="mt-4"
              icon={<X className="w-3.5 h-3.5 text-rose-400" />}
              onClick={handleClearFilters}
            >
              Clear Filters
            </Button>
          </Card>
        ) : (
          /* TOTAL EMPTY STATE */
          <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
            <Lightbulb className="w-10 h-10 text-indigo-400/70 mb-3" />
            <h4 className="text-sm font-semibold text-slate-200">No content ideas yet.</h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Create your first topic idea to start building the MochyFami video production pipeline.
            </p>
            <Button
              variant="primary"
              size="sm"
              className="mt-4"
              icon={<Plus className="w-4 h-4 text-white" />}
              onClick={() => onNavigate('/ideas/new')}
            >
              Create First Idea
            </Button>
          </Card>
        )
      ) : (
        /* SUCCESS GRID STATE */
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {ideas.map((idea) => (
            <IdeaCard
              key={idea.id}
              idea={idea}
              onEdit={(item) => onNavigate(`/ideas/edit/${item.id}`)}
              onDelete={(item) => setDeleteModalIdea(item)}
              onConvertToProject={(item) => setConvertModalIdea(item)}
              onOpenProject={(item) => item.project_id && onNavigate(`/projects/${item.project_id}`)}
            />
          ))}
        </div>
      )}

      {/* Pagination Controls */}
      {pagination.last_page > 1 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400 pt-4 border-t border-slate-800/80">
          <div>
            Showing page <span className="font-semibold text-slate-200">{pagination.current_page}</span> of{' '}
            <span className="font-semibold text-slate-200">{pagination.last_page}</span> ({pagination.total} total ideas)
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page === 1}
              onClick={() => handleFilterChange({ ...filters, page: pagination.current_page - 1 })}
              icon={<ChevronLeft className="w-4 h-4" />}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page === pagination.last_page}
              onClick={() => handleFilterChange({ ...filters, page: pagination.current_page + 1 })}
              icon={<ChevronRight className="w-4 h-4" />}
            >
              Next
            </Button>
          </div>
        </div>
      )}

      {/* Convert to Project Modal */}
      {convertModalIdea && (
        <ConvertIdeaModal
          idea={convertModalIdea}
          onClose={() => setConvertModalIdea(null)}
          onSuccess={handleConvertSuccess}
          onAlreadyConverted={handleAlreadyConverted}
        />
      )}

      {/* Delete Confirmation Modal */}
      {deleteModalIdea && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Content Idea?</h3>
                <p className="text-xs text-slate-400">This action cannot be undone.</p>
              </div>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              Are you sure you want to delete <span className="font-bold text-white">"{deleteModalIdea.title}"</span>?
            </p>

            <div className="flex items-center justify-end gap-3">
              <Button variant="outline" size="sm" disabled={deleting} onClick={() => setDeleteModalIdea(null)}>
                Cancel
              </Button>
              <Button
                variant="danger"
                size="sm"
                isLoading={deleting}
                onClick={() => handleDelete(deleteModalIdea)}
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
