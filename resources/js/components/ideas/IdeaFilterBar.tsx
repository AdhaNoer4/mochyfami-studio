import React, { useEffect, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { ContentCategory, IdeaFilterParams, IdeaSortDirection, IdeaSortField } from '../../types';
import { Search, X, ArrowUp, ArrowDown } from 'lucide-react';

export interface IdeaFilterBarProps {
  categories: ContentCategory[];
  filters: IdeaFilterParams;
  onChange: (updatedFilters: IdeaFilterParams) => void;
  onClear: () => void;
}

export const DEFAULT_IDEA_FILTERS: IdeaFilterParams = {
  page: 1,
  search: '',
  category_id: '',
  format: '',
  status: '',
  priority: '',
  sort: 'created_at',
  direction: 'desc',
  per_page: 10,
};

export const IdeaFilterBar: React.FC<IdeaFilterBarProps> = ({
  categories,
  filters,
  onChange,
  onClear,
}) => {
  const [searchInput, setSearchInput] = useState<string>(filters.search || '');

  // Keep local search input synced with incoming filters (e.g. when cleared or URL loaded)
  useEffect(() => {
    setSearchInput(filters.search || '');
  }, [filters.search]);

  // 400ms Debounce search effect
  useEffect(() => {
    const handler = setTimeout(() => {
      if (searchInput !== (filters.search || '')) {
        onChange({ ...filters, search: searchInput, page: 1 });
      }
    }, 400);

    return () => clearTimeout(handler);
  }, [searchInput]);

  // Check if current filters differ from defaults (to show Clear Filters button)
  const isFiltered = Boolean(
    (filters.search && filters.search.trim() !== '') ||
      filters.category_id ||
      filters.format ||
      filters.status ||
      filters.priority ||
      (filters.sort && filters.sort !== 'created_at') ||
      (filters.direction && filters.direction !== 'desc') ||
      (filters.per_page && filters.per_page !== 10)
  );

  const handleFilterChange = (key: keyof IdeaFilterParams, value: any) => {
    onChange({
      ...filters,
      [key]: value,
      page: 1, // Reset to page 1 on any filter change
    });
  };

  const toggleDirection = () => {
    const newDirection: IdeaSortDirection = filters.direction === 'asc' ? 'desc' : 'asc';
    handleFilterChange('direction', newDirection);
  };

  return (
    <Card variant="subtle" className="p-4 space-y-4">
      {/* Search Input Row */}
      <div className="flex flex-col sm:flex-row items-center gap-3">
        <div className="relative flex-1 w-full">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search idea title, hook, or concept..."
            className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-9 pr-9 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
          />
          {searchInput && (
            <button
              onClick={() => setSearchInput('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300"
              title="Clear search keyword"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          )}
        </div>

        {/* Clear Filters Button (shown only when filtered) */}
        {isFiltered && (
          <Button
            variant="ghost"
            size="sm"
            onClick={onClear}
            icon={<X className="w-3.5 h-3.5 text-rose-400" />}
            className="text-xs text-rose-400 hover:bg-rose-500/10 shrink-0"
          >
            Clear Filters
          </Button>
        )}
      </div>

      {/* Multi-Filter & Sort Selectors Row */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-3 border-t border-slate-800/60">
        {/* Category Filter */}
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Category
          </label>
          <select
            value={filters.category_id || ''}
            onChange={(e) => handleFilterChange('category_id', e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
          >
            <option value="">All Categories</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>

        {/* Format Filter */}
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Format
          </label>
          <select
            value={filters.format || ''}
            onChange={(e) => handleFilterChange('format', e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
          >
            <option value="">All Formats</option>
            <option value="educational">Educational</option>
            <option value="funny_fact">Funny Fact</option>
            <option value="storytelling">Storytelling</option>
            <option value="comparison">Comparison</option>
            <option value="pov">POV</option>
            <option value="list">Listicle</option>
          </select>
        </div>

        {/* Status Filter */}
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Status
          </label>
          <select
            value={filters.status || ''}
            onChange={(e) => handleFilterChange('status', e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
          >
            <option value="">All Statuses</option>
            <option value="idea">Idea Backlog</option>
            <option value="selected">Selected</option>
            <option value="converted">Converted</option>
            <option value="archived">Archived</option>
          </select>
        </div>

        {/* Priority Filter */}
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Priority
          </label>
          <select
            value={filters.priority || ''}
            onChange={(e) => handleFilterChange('priority', e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
          >
            <option value="">All Priorities</option>
            <option value="3">High Priority</option>
            <option value="2">Medium Priority</option>
            <option value="1">Low Priority</option>
          </select>
        </div>

        {/* Sort Field & Direction */}
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Sort By
          </label>
          <div className="flex items-center gap-1">
            <select
              value={filters.sort || 'created_at'}
              onChange={(e) => handleFilterChange('sort', e.target.value as IdeaSortField)}
              className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
            >
              <option value="created_at">Date Created</option>
              <option value="updated_at">Date Updated</option>
              <option value="title">Title</option>
              <option value="priority">Priority</option>
            </select>
            <button
              type="button"
              onClick={toggleDirection}
              className="p-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 hover:text-white shrink-0"
              title={`Toggle direction (${filters.direction || 'desc'})`}
            >
              {filters.direction === 'asc' ? <ArrowUp className="w-3.5 h-3.5" /> : <ArrowDown className="w-3.5 h-3.5" />}
            </button>
          </div>
        </div>

        {/* Items Per Page */}
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Per Page
          </label>
          <select
            value={filters.per_page || 10}
            onChange={(e) => handleFilterChange('per_page', parseInt(e.target.value, 10))}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
          >
            <option value={10}>10 items</option>
            <option value={25}>25 items</option>
            <option value={50}>50 items</option>
          </select>
        </div>
      </div>
    </Card>
  );
};
