import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card } from '../../components/ui/Card';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { ProjectCard } from '../../components/projects/ProjectCard';
import { ProjectFilterBar, DEFAULT_PROJECT_FILTERS } from '../../components/projects/ProjectFilterBar';
import { projectService } from '../../services/projectService';
import { categoryService } from '../../services/categoryService';
import { ContentCategory, ContentProject, ProjectFilterParams, PaginationMeta } from '../../types';
import {
  Plus,
  Clapperboard,
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  X,
  RefreshCw,
  FilterX,
} from 'lucide-react';

interface ProjectsListPageProps {
  onNavigate: (path: string) => void;
}

export const ProjectsListPage: React.FC<ProjectsListPageProps> = ({ onNavigate }) => {
  const [projects, setProjects] = useState<ContentProject[]>([]);
  const [categories, setCategories] = useState<ContentCategory[]>([]);
  const [pagination, setPagination] = useState<PaginationMeta>({
    total: 0,
    per_page: 10,
    current_page: 1,
    last_page: 1,
  });

  // Parse initial filters from browser URL
  const getInitialFilters = (): ProjectFilterParams => {
    const params = new URLSearchParams(window.location.search);
    return {
      page: parseInt(params.get('page') || '1', 10),
      per_page: parseInt(params.get('per_page') || '10', 10),
      search: params.get('search') || '',
      status: params.get('status') || '',
      category_id: params.get('category_id') || '',
      sort: (params.get('sort') as any) || 'created_at',
      direction: (params.get('direction') as any) || 'desc',
    };
  };

  const [filters, setFilters] = useState<ProjectFilterParams>(getInitialFilters);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Delete modal state
  const [deleteModalProject, setDeleteModalProject] = useState<ContentProject | null>(null);
  const [deleting, setDeleting] = useState<boolean>(false);

  // Load categories for filter dropdown
  useEffect(() => {
    categoryService.getCategories(1, '', 100).then((data) => {
      setCategories(data.items);
    });
  }, []);

  // Sync state to URL parameters
  const syncFiltersToUrl = (newFilters: ProjectFilterParams) => {
    const params = new URLSearchParams();
    if (newFilters.page && newFilters.page > 1) params.set('page', String(newFilters.page));
    if (newFilters.per_page && newFilters.per_page !== 10) params.set('per_page', String(newFilters.per_page));
    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.status) params.set('status', newFilters.status);
    if (newFilters.category_id) params.set('category_id', String(newFilters.category_id));
    if (newFilters.sort && newFilters.sort !== 'created_at') params.set('sort', newFilters.sort);
    if (newFilters.direction && newFilters.direction !== 'desc') params.set('direction', newFilters.direction);

    const queryString = params.toString();
    const newUrl = `${window.location.pathname}${queryString ? `?${queryString}` : ''}`;
    window.history.replaceState({}, '', newUrl);
  };

  const fetchProjects = async (currentFilters = filters) => {
    setLoading(true);
    setError(null);
    try {
      const data = await projectService.getProjects(currentFilters);
      setProjects(data.items);
      setPagination(data.pagination);
    } catch (err: unknown) {
      console.error('Failed to load projects:', err);
      setError('Unable to load projects from server. Please check connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    syncFiltersToUrl(filters);
    fetchProjects(filters);
  }, [
    filters.page,
    filters.per_page,
    filters.search,
    filters.status,
    filters.category_id,
    filters.sort,
    filters.direction,
  ]);

  const handleFilterChange = (updatedFilters: ProjectFilterParams) => {
    setFilters(updatedFilters);
  };

  const handleClearFilters = () => {
    setFilters(DEFAULT_PROJECT_FILTERS);
  };

  const handleDelete = async (project: ContentProject) => {
    setDeleting(true);
    try {
      await projectService.deleteProject(project.id);
      setDeleteModalProject(null);
      await fetchProjects(filters);
    } catch (err: unknown) {
      console.error('Failed to delete project:', err);
    } finally {
      setDeleting(false);
    }
  };

  const isFiltered = Boolean(
    (filters.search && filters.search.trim() !== '') ||
      filters.status ||
      filters.category_id ||
      (filters.sort && filters.sort !== 'created_at') ||
      (filters.direction && filters.direction !== 'desc')
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Content Projects"
        description="Manage video production containers, step workflows, and status pipelines."
        badge={<Badge variant="indigo">{pagination.total} Projects</Badge>}
        action={
          <Button
            variant="primary"
            size="sm"
            icon={<Plus className="w-4 h-4 text-white" />}
            onClick={() => onNavigate('/projects/new')}
          >
            New Project
          </Button>
        }
      />

      {/* Filter Toolbar */}
      <ProjectFilterBar
        categories={categories}
        filters={filters}
        onChange={handleFilterChange}
        onClear={handleClearFilters}
      />

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
              onClick={() => fetchProjects(filters)}
            >
              Try Again
            </Button>
          </div>
        </Card>
      )}

      {/* Projects Grid Content */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div key={i} className="h-48 bg-slate-900 border border-slate-800 rounded-xl animate-pulse p-6" />
          ))}
        </div>
      ) : !error && projects.length === 0 ? (
        isFiltered ? (
          /* FILTERED EMPTY STATE */
          <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
            <FilterX className="w-10 h-10 text-amber-400/70 mb-3" />
            <h4 className="text-sm font-semibold text-slate-200">No projects match your current filters.</h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Try adjusting your search query, status filter, or category filter to view results.
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
            <Clapperboard className="w-10 h-10 text-indigo-400/70 mb-3" />
            <h4 className="text-sm font-semibold text-slate-200">No content projects created yet.</h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Create a new content project to manage your video production pipeline from research to publishing.
            </p>
            <Button
              variant="primary"
              size="sm"
              className="mt-4"
              icon={<Plus className="w-4 h-4 text-white" />}
              onClick={() => onNavigate('/projects/new')}
            >
              Create First Project
            </Button>
          </Card>
        )
      ) : (
        /* SUCCESS GRID STATE */
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {projects.map((project) => (
            <ProjectCard
              key={project.id}
              project={project}
              onNavigate={onNavigate}
              onEdit={(item) => onNavigate(`/projects/${item.id}/edit`)}
              onDelete={(item) => setDeleteModalProject(item)}
            />
          ))}
        </div>
      )}

      {/* Pagination Controls */}
      {pagination.last_page > 1 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400 pt-4 border-t border-slate-800/80">
          <div>
            Showing page <span className="font-semibold text-slate-200">{pagination.current_page}</span> of{' '}
            <span className="font-semibold text-slate-200">{pagination.last_page}</span> ({pagination.total} total projects)
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

      {/* Delete Confirmation Modal */}
      {deleteModalProject && (
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
              Are you sure you want to delete project <span className="font-bold text-white">"{deleteModalProject.title}"</span>?
            </p>

            <div className="flex items-center justify-end gap-3">
              <Button variant="outline" size="sm" disabled={deleting} onClick={() => setDeleteModalProject(null)}>
                Cancel
              </Button>
              <Button
                variant="danger"
                size="sm"
                isLoading={deleting}
                onClick={() => handleDelete(deleteModalProject)}
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
