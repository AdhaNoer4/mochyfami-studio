import React, { useCallback, useEffect, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { Asset, AssetFilterParams } from '../../types';
import { assetService } from '../../services/assetService';
import { AssetFilterBar, DEFAULT_ASSET_FILTERS } from '../assets/AssetFilterBar';
import { AssetCard } from '../assets/AssetCard';
import { AssetFormModal } from '../assets/AssetFormModal';
import { AssetDetailModal } from '../assets/AssetDetailModal';
import { AssetUploadModal } from '../assets/AssetUploadModal';
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  FolderOpen,
  FilterX,
  Plus,
  AlertCircle,
} from 'lucide-react';

export interface AssetLibraryPanelProps {
  projectId: number;
}

const EMPTY_PAGINATION = {
  total: 0,
  per_page: 10,
  current_page: 1,
  last_page: 1,
};

/**
 * Prefers the server's own message over the axios status line. AxiosError's
 * top level message is only "Request failed with status code 403", which tells
 * the user nothing about why the list did not load.
 */
function readErrorMessage(error: unknown, fallback: string): string {
  const response = (
    error as { response?: { data?: { message?: string } } } | null
  )?.response?.data;

  return response?.message || fallback;
}

export const AssetLibraryPanel: React.FC<AssetLibraryPanelProps> = ({ projectId }) => {
  const [assets, setAssets] = useState<Asset[]>([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [filters, setFilters] = useState<AssetFilterParams>(DEFAULT_ASSET_FILTERS);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const [formAsset, setFormAsset] = useState<Asset | null>(null);
  const [formOpen, setFormOpen] = useState<boolean>(false);
  const [detailAsset, setDetailAsset] = useState<Asset | null>(null);
  const [uploadAsset, setUploadAsset] = useState<Asset | null>(null);
  const [deleteAsset, setDeleteAsset] = useState<Asset | null>(null);
  const [deleting, setDeleting] = useState<boolean>(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  // Bumped after a write so the list reloads through the same effect every
  // other read goes through, rather than a second hand-rolled fetch path.
  const [reloadKey, setReloadKey] = useState<number>(0);

  const reload = useCallback(() => {
    setReloadKey((key) => key + 1);
  }, []);

  useEffect(() => {
    // The active flag drops a response that lands after the filters changed or
    // the tab closed, so a slow first request cannot overwrite a newer one.
    let active = true;

    (async () => {
      setLoading(true);
      setError(null);

      try {
        const data = await assetService.list(projectId, filters);
        if (!active) return;
        setAssets(data.items);
        setPagination(data.pagination);
      } catch (err) {
        if (!active) return;
        setAssets([]);
        setPagination(EMPTY_PAGINATION);
        setError(readErrorMessage(err, 'Unable to load the asset library.'));
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [projectId, filters, reloadKey]);

  const handleFilterChange = (updated: AssetFilterParams) => {
    setFilters(updated);
  };

  const handleClearFilters = () => {
    setFilters(DEFAULT_ASSET_FILTERS);
  };

  const openCreateModal = () => {
    setFormAsset(null);
    setFormOpen(true);
  };

  const openEditModal = (asset: Asset) => {
    setFormAsset(asset);
    setFormOpen(true);
  };

  const handleSaved = (saved: Asset) => {
    setFormOpen(false);
    setFormAsset(null);
    setSuccessMessage(formAsset ? 'Asset metadata updated successfully.' : 'Asset added to the library.');
    reload();
    if (!formAsset) {
      setDetailAsset(saved);
    }
  };

  const handleUploaded = (updated: Asset) => {
    setUploadAsset(null);
    setSuccessMessage(
      updated.file_name
        ? `File uploaded successfully: ${updated.file_name}.`
        : 'File uploaded successfully.',
    );
    // The list is reloaded rather than patched in place: an upload changes
    // file metadata that a filter or sort may depend on, and the server is
    // the only one who knows the resulting order.
    reload();
    setDetailAsset(updated);
  };

  const handleDelete = async () => {
    if (!deleteAsset) return;

    setDeleting(true);
    setDeleteError(null);

    try {
      await assetService.remove(projectId, deleteAsset.id);
      setDeleteAsset(null);
      setSuccessMessage('Asset deleted successfully.');
      reload();
    } catch (err) {
      setDeleteError(readErrorMessage(err, 'Unable to delete this asset.'));
    } finally {
      setDeleting(false);
    }
  };

  const isFiltered = Boolean(
    (filters.search && filters.search.trim() !== '') ||
      filters.type ||
      filters.status ||
      (filters.sort && filters.sort !== 'created_at') ||
      (filters.direction && filters.direction !== 'desc') ||
      (filters.per_page && filters.per_page !== 10),
  );

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-white">Asset Library</h3>
          <p className="text-[11px] text-slate-400 mt-0.5">
            Metadata only. No file is uploaded, stored, or previewed from this screen.
          </p>
        </div>

        <Button
          variant="primary"
          size="sm"
          onClick={openCreateModal}
          icon={<Plus className="w-4 h-4 text-white" />}
        >
          Add Asset
        </Button>
      </div>

      {successMessage && (
        <div className="flex items-center gap-2 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-300 text-xs">
          <FolderOpen className="w-4 h-4 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      <AssetFilterBar
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
              icon={<AlertCircle className="w-3.5 h-3.5" />}
              onClick={reload}
            >
              Try Again
            </Button>
          </div>
        </Card>
      )}

      {/* Asset Grid */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div
              key={i}
              className="h-52 bg-slate-900 border border-slate-800 rounded-xl animate-pulse p-6"
            />
          ))}
        </div>
      ) : !error && assets.length === 0 ? (
        isFiltered ? (
          <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
            <FilterX className="w-10 h-10 text-amber-400/70 mb-3" />
            <h4 className="text-sm font-semibold text-slate-200">
              No assets match your current filters.
            </h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Try a different search keyword, type, or status filter.
            </p>
            <Button
              variant="outline"
              size="sm"
              className="mt-4"
              onClick={handleClearFilters}
            >
              Clear Filters
            </Button>
          </Card>
        ) : (
          <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
            <FolderOpen className="w-10 h-10 text-violet-400/70 mb-3" />
            <h4 className="text-sm font-semibold text-slate-200">No assets recorded yet.</h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Record what media this project already has. Every field is metadata you type in; nothing
              is uploaded or fetched for you.
            </p>
            <Button
              variant="primary"
              size="sm"
              className="mt-4"
              icon={<Plus className="w-4 h-4 text-white" />}
              onClick={openCreateModal}
            >
              Add First Asset
            </Button>
          </Card>
        )
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {assets.map((asset) => (
            <AssetCard
              key={asset.id}
              asset={asset}
              onView={setDetailAsset}
              onEdit={openEditModal}
              onUploadFile={setUploadAsset}
              onDelete={setDeleteAsset}
            />
          ))}
        </div>
      )}

      {/* Pagination Controls */}
      {pagination.last_page > 1 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400 pt-4 border-t border-slate-800/80">
          <div>
            Showing page <span className="font-semibold text-slate-200">{pagination.current_page}</span>{' '}
            of <span className="font-semibold text-slate-200">{pagination.last_page}</span> (
            {pagination.total} total assets)
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

      {/* Create / Edit Modal */}
      {formOpen && (
        <AssetFormModal
          projectId={projectId}
          asset={formAsset}
          onClose={() => {
            setFormOpen(false);
            setFormAsset(null);
          }}
          onSaved={handleSaved}
        />
      )}

      {/* Upload Modal */}
      {uploadAsset && (
        <AssetUploadModal
          projectId={projectId}
          asset={uploadAsset}
          onClose={() => setUploadAsset(null)}
          onUploaded={handleUploaded}
        />
      )}

      {/* Detail Modal */}
      {detailAsset && <AssetDetailModal asset={detailAsset} onClose={() => setDetailAsset(null)} />}

      {/* Delete Confirmation Modal */}
      {deleteAsset && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Asset Metadata?</h3>
                <p className="text-xs text-slate-400">This action cannot be undone.</p>
              </div>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              Delete{' '}
              <span className="font-bold text-white">
                {deleteAsset.title || deleteAsset.file_name || `asset #${deleteAsset.id}`}
              </span>
              ? The metadata record is removed and the stored file is deleted with it. If the file
              cannot be deleted, the record still goes: nothing is left pointing at a file that may
              be orphaned on disk.
            </p>

            {deleteError && (
              <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs mb-4">
                <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                <span>{deleteError}</span>
              </div>
            )}

            <div className="flex items-center justify-end gap-3">
              <Button
                variant="outline"
                size="sm"
                disabled={deleting}
                onClick={() => {
                  setDeleteAsset(null);
                  setDeleteError(null);
                }}
              >
                Cancel
              </Button>
              <Button variant="danger" size="sm" isLoading={deleting} onClick={handleDelete}>
                Confirm Delete
              </Button>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
};
