import React from 'react';
import { ProjectFilterParams, ContentCategory, ProjectSortField } from '../../types';
import { Card } from '../ui/Card';
import { Search, ArrowUpDown, X } from 'lucide-react';

export interface ProjectFilterBarProps {
  categories: ContentCategory[];
  filters: ProjectFilterParams;
  onChange: (filters: ProjectFilterParams) => void;
  onClear: () => void;
}

export const DEFAULT_PROJECT_FILTERS: ProjectFilterParams = {
  page: 1,
  per_page: 10,
  search: '',
  status: '',
  category_id: '',
  sort: 'created_at',
  direction: 'desc',
};

export const ProjectFilterBar: React.FC<ProjectFilterBarProps> = ({
  categories,
  filters,
  onChange,
  onClear,
}) => {
  const isFiltered = Boolean(
    (filters.search && filters.search.trim() !== '') ||
      filters.status ||
      filters.category_id ||
      (filters.sort && filters.sort !== 'created_at') ||
      (filters.direction && filters.direction !== 'desc')
  );

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onChange({ ...filters, search: e.target.value, page: 1 });
  };

  const handleStatusChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onChange({ ...filters, status: e.target.value, page: 1 });
  };

  const handleCategoryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onChange({ ...filters, category_id: e.target.value, page: 1 });
  };

  const handleSortChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onChange({ ...filters, sort: e.target.value as ProjectSortField, page: 1 });
  };

  const toggleSortDirection = () => {
    onChange({
      ...filters,
      direction: filters.direction === 'asc' ? 'desc' : 'asc',
      page: 1,
    });
  };

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onChange({ ...filters, per_page: parseInt(e.target.value, 10), page: 1 });
  };

  return (
    <Card variant="subtle" className="p-4 space-y-3">
      <div className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-3">
        {/* Search Input */}
        <div className="relative md:col-span-2">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={filters.search || ''}
            onChange={handleSearchChange}
            placeholder="Search projects..."
            className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-9 pr-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors"
          />
        </div>

        {/* Status Dropdown */}
        <div>
          <select
            value={filters.status || ''}
            onChange={handleStatusChange}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition-colors"
          >
            <option value="">All Statuses</option>
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

        {/* Category Dropdown */}
        <div>
          <select
            value={filters.category_id || ''}
            onChange={handleCategoryChange}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition-colors"
          >
            <option value="">All Categories</option>
            {categories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name}
              </option>
            ))}
          </select>
        </div>

        {/* Sort Controls */}
        <div className="flex items-center gap-2">
          <select
            value={filters.sort || 'created_at'}
            onChange={handleSortChange}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition-colors"
          >
            <option value="created_at">Sort by Date</option>
            <option value="title">Sort by Title</option>
            <option value="status">Sort by Status</option>
            <option value="progress_percent">Sort by Progress</option>
          </select>

          <button
            onClick={toggleSortDirection}
            className="p-2 bg-slate-950 border border-slate-800 rounded-lg hover:border-slate-700 text-slate-300 transition-colors shrink-0"
            title={`Direction: ${filters.direction === 'asc' ? 'Ascending' : 'Descending'}`}
          >
            <ArrowUpDown className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      <div className="flex items-center justify-between pt-2 border-t border-slate-800/60 text-xs text-slate-400">
        <div className="flex items-center gap-3">
          <span>Items per page:</span>
          <select
            value={filters.per_page || 10}
            onChange={handlePerPageChange}
            className="bg-slate-950 border border-slate-800 rounded px-2 py-1 text-xs text-slate-200"
          >
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>

        {isFiltered && (
          <button
            onClick={onClear}
            className="flex items-center gap-1.5 text-indigo-400 hover:text-indigo-300 font-medium transition-colors"
          >
            <X className="w-3.5 h-3.5" /> Clear Filters
          </button>
        )}
      </div>
    </Card>
  );
};
