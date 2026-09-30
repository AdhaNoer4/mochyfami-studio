import React, { useEffect, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import {
  AssetFilterParams,
  AssetSortDirection,
  AssetSortField,
  AssetStatus,
  AssetType,
} from '../../types';
import { Search, X, ArrowUp, ArrowDown } from 'lucide-react';

export interface AssetFilterBarProps {
  filters: AssetFilterParams;
  onChange: (updatedFilters: AssetFilterParams) => void;
  onClear: () => void;
}

export const DEFAULT_ASSET_FILTERS: AssetFilterParams = {
  page: 1,
  per_page: 10,
  search: '',
  type: '',
  status: '',
  sort: 'created_at',
  direction: 'desc',
};

/**
 * Only the options the backend accepts are offered here. The type and status
 * lists mirror the AssetType and AssetStatus enums, and the sort list is the
 * allowlist GetAssetsRequest enforces, so a choice here cannot produce a 422.
 */
const TYPE_OPTIONS: { value: AssetType; label: string }[] = [
  { value: 'video', label: 'Video' },
  { value: 'image', label: 'Image' },
  { value: 'audio', label: 'Audio' },
  { value: 'other', label: 'Other' },
];

const STATUS_OPTIONS: { value: AssetStatus; label: string }[] = [
  { value: 'pending', label: 'Pending' },
  { value: 'available', label: 'Available' },
  { value: 'processing', label: 'Processing' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'archived', label: 'Archived' },
];

export const AssetFilterBar: React.FC<AssetFilterBarProps> = ({ filters, onChange, onClear }) => {
  const [searchInput, setSearchInput] = useState<string>(filters.search || '');

  // Keep local search input synced with incoming filters (e.g. when cleared)
  useEffect(() => {
    setSearchInput(filters.search || '');
  }, [filters.search]);

  // 400ms debounce. Without it every keystroke would refetch the whole list,
  // which is the behaviour the idea list deliberately avoids.
  useEffect(() => {
    const handler = setTimeout(() => {
      if (searchInput !== (filters.search || '')) {
        onChange({ ...filters, search: searchInput, page: 1 });
      }
    }, 400);

    return () => clearTimeout(handler);
  }, [searchInput]);

  const isFiltered = Boolean(
    (filters.search && filters.search.trim() !== '') ||
      filters.type ||
      filters.status ||
      (filters.sort && filters.sort !== 'created_at') ||
      (filters.direction && filters.direction !== 'desc') ||
      (filters.per_page && filters.per_page !== 10),
  );

  const handleFilterChange = (key: keyof AssetFilterParams, value: string | number) => {
    onChange({
      ...filters,
      [key]: value,
      page: 1,
    });
  };

  const toggleDirection = () => {
    const newDirection: AssetSortDirection = filters.direction === 'asc' ? 'desc' : 'asc';
    handleFilterChange('direction', newDirection);
  };

  const selectClasses =
    'w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500';

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
            placeholder="Search title, file name, source, or notes..."
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

      {/* Filter & Sort Selectors Row */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-800/60">
        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Type
          </label>
          <select
            value={filters.type || ''}
            onChange={(e) => handleFilterChange('type', e.target.value)}
            className={selectClasses}
          >
            <option value="">All Types</option>
            {TYPE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Status
          </label>
          <select
            value={filters.status || ''}
            onChange={(e) => handleFilterChange('status', e.target.value)}
            className={selectClasses}
          >
            <option value="">All Statuses</option>
            {STATUS_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Sort By
          </label>
          <div className="flex items-center gap-1">
            <select
              value={filters.sort || 'created_at'}
              onChange={(e) => handleFilterChange('sort', e.target.value as AssetSortField)}
              className={selectClasses}
            >
              <option value="created_at">Newest</option>
              <option value="updated_at">Recently Updated</option>
              <option value="title">Title</option>
              <option value="file_size">Largest File</option>
              <option value="duration_seconds">Longest Duration</option>
            </select>
            <button
              type="button"
              onClick={toggleDirection}
              className="p-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 hover:text-white shrink-0"
              title={`Toggle direction (${filters.direction || 'desc'})`}
            >
              {filters.direction === 'asc' ? (
                <ArrowUp className="w-3.5 h-3.5" />
              ) : (
                <ArrowDown className="w-3.5 h-3.5" />
              )}
            </button>
          </div>
        </div>

        <div>
          <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
            Per Page
          </label>
          <select
            value={filters.per_page || 10}
            onChange={(e) => handleFilterChange('per_page', parseInt(e.target.value, 10))}
            className={selectClasses}
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
