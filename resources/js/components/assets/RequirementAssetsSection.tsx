import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Asset, AssetType } from '../../types';
import { assetService } from '../../services/assetService';
import { assetRequirementService } from '../../services/assetRequirementService';
import { getApiErrorMessage } from '../../services/researchService';
import { getAssetStatusVariant, formatFileSize, formatDimensions } from './AssetCard';
import {
  AlertCircle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  FileText,
  Image as ImageIcon,
  Link2,
  Loader2,
  Music,
  Plus,
  Search,
  Video,
  X,
} from 'lucide-react';

export interface RequirementAssetsSectionProps {
  projectId: number;
  version: number;
  itemId: number;
  requirementId: number;
  requirementLabel: string;
}

const PICKER_PER_PAGE = 10;

const TYPE_ICONS: Record<AssetType, React.ElementType> = {
  video: Video,
  image: ImageIcon,
  audio: Music,
  other: FileText,
};

function getTypeIcon(type: AssetType) {
  return TYPE_ICONS[type] || FileText;
}

function assetLabel(asset: Asset): string {
  return asset.title || asset.file_name || `Asset #${asset.id}`;
}

/**
 * A candidate is not a fulfillment.
 *
 * Nothing in this component advances a status, marks the requirement
 * fulfilled, or picks a winner, because the association it manages carries none
 * of that meaning. The wording below is deliberately "candidate" for the same
 * reason the API and the pivot are: this list records who could satisfy the
 * requirement, never that somebody did.
 */
export const RequirementAssetsSection: React.FC<RequirementAssetsSectionProps> = ({
  projectId,
  version,
  itemId,
  requirementId,
  requirementLabel,
}) => {
  const [assets, setAssets] = useState<Asset[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [busyAssetId, setBusyAssetId] = useState<number | null>(null);

  const [pickerOpen, setPickerOpen] = useState<boolean>(false);
  const [search, setSearch] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const [pickerAssets, setPickerAssets] = useState<Asset[]>([]);
  const [pickerLoading, setPickerLoading] = useState<boolean>(false);
  const [pickerError, setPickerError] = useState<string | null>(null);
  const [pickerTotal, setPickerTotal] = useState<number>(0);
  const [pickerLastPage, setPickerLastPage] = useState<number>(1);

  // Bumped after a write so the associated list reloads through the same
  // effect every other read goes through, instead of a second fetch path.
  const [reloadKey, setReloadKey] = useState<number>(0);

  const reload = useCallback(() => {
    setReloadKey((key) => key + 1);
  }, []);

  useEffect(() => {
    // The active flag drops a response that lands after the requirement
    // changed, so a slow first request cannot overwrite a newer list.
    let active = true;

    (async () => {
      setLoading(true);
      setError(null);

      try {
        const data = await assetRequirementService.listAssets(
          projectId,
          version,
          itemId,
          requirementId,
        );
        if (!active) return;
        setAssets(data);
      } catch (err) {
        if (!active) return;
        setAssets([]);
        setError(getApiErrorMessage(err, 'Unable to load the associated assets.'));
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [projectId, version, itemId, requirementId, reloadKey]);

  const attachedIds = useMemo(() => new Set(assets.map((asset) => asset.id)), [assets]);

  useEffect(() => {
    if (!pickerOpen) return;

    let active = true;

    (async () => {
      setPickerLoading(true);
      setPickerError(null);

      try {
        // The picker reuses the Asset Library API rather than searching on its
        // own, so search, paging, and project scope stay identical to the
        // library instead of becoming a second implementation of both.
        const data = await assetService.list(projectId, {
          search: search || undefined,
          page,
          per_page: PICKER_PER_PAGE,
        });
        if (!active) return;
        setPickerAssets(data.items);
        setPickerTotal(data.pagination.total);
        setPickerLastPage(Math.max(1, data.pagination.last_page));
      } catch (err) {
        if (!active) return;
        setPickerAssets([]);
        setPickerError(getApiErrorMessage(err, 'Unable to load the asset library.'));
      } finally {
        if (active) setPickerLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [projectId, pickerOpen, search, page]);

  const handleAttach = async (asset: Asset) => {
    setBusyAssetId(asset.id);
    setError(null);

    try {
      await assetRequirementService.attachAsset(projectId, version, itemId, requirementId, asset.id);
      // Reloaded rather than appended, so the list is always what the server
      // considers associated and never drifts from it.
      reload();
      setPage(1);
    } catch (err) {
      setError(getApiErrorMessage(err, 'Unable to attach the asset to this requirement.'));
      reload();
    } finally {
      setBusyAssetId(null);
    }
  };

  const handleDetach = async (asset: Asset) => {
    setBusyAssetId(asset.id);
    setError(null);

    try {
      await assetRequirementService.detachAsset(projectId, version, itemId, requirementId, asset.id);
      reload();
    } catch (err) {
      setError(getApiErrorMessage(err, 'Unable to detach the asset from this requirement.'));
      reload();
    } finally {
      setBusyAssetId(null);
    }
  };

  const renderAttachedRow = (asset: Asset) => {
    const TypeIcon = getTypeIcon(asset.type);
    const fileSize = formatFileSize(asset.file_size);
    const dimensions = formatDimensions(asset);

    return (
      <div
        key={asset.id}
        className="flex items-start gap-2 p-2 rounded-lg bg-slate-900/60 border border-slate-800"
      >
        <div className="p-1.5 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 shrink-0">
          <TypeIcon className="w-3.5 h-3.5" />
        </div>

        <div className="min-w-0 flex-1">
          <p className="text-[11px] font-semibold text-slate-200 truncate" title={assetLabel(asset)}>
            {assetLabel(asset)}
          </p>

          <div className="flex flex-wrap items-center gap-1.5 mt-1">
            <Badge variant="violet" size="sm">
              {asset.type_label}
            </Badge>
            <Badge variant={getAssetStatusVariant(asset.status)} size="sm">
              {asset.status_label}
            </Badge>
            {(fileSize || dimensions) && (
              <span className="text-[10px] text-slate-500">
                {[fileSize, dimensions].filter(Boolean).join(' - ')}
              </span>
            )}
          </div>
        </div>

        <Button
          variant="ghost"
          size="sm"
          disabled={busyAssetId === asset.id}
          onClick={() => handleDetach(asset)}
          icon={busyAssetId === asset.id ? undefined : <X className="w-3.5 h-3.5" />}
          className="text-slate-400 hover:text-rose-400 shrink-0"
        >
          Detach
        </Button>
      </div>
    );
  };

  const renderPicker = () => {
    if (!pickerOpen) return null;

    return (
      <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
        <div className="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl my-8">
          <div className="flex items-start justify-between gap-4 p-5 border-b border-slate-800">
            <div className="min-w-0">
              <h3 className="text-sm font-semibold text-white">Attach a candidate asset</h3>
              <p className="text-[11px] text-slate-500 mt-0.5 truncate">{requirementLabel}</p>
            </div>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPickerOpen(false)}
              className="text-slate-400 hover:text-white shrink-0"
            >
              Close
            </Button>
          </div>

          <div className="p-5 space-y-4">
            <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-800">
              <Search className="w-4 h-4 text-slate-500 shrink-0" />
              <input
                type="text"
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value);
                  setPage(1);
                }}
                placeholder="Search this project's assets"
                className="flex-1 bg-transparent text-xs text-slate-200 placeholder:text-slate-600 focus:outline-none"
              />
            </div>

            <p className="text-[11px] text-slate-500">
              Assets are metadata only in this part. Uploading a file, or previewing one, is not
              available here yet.
            </p>

            {pickerError && (
              <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
                <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                <span>{pickerError}</span>
              </div>
            )}

            {pickerLoading ? (
              <div className="flex items-center justify-center gap-2 py-8 text-xs text-slate-500">
                <Loader2 className="w-4 h-4 animate-spin" />
                Loading assets...
              </div>
            ) : pickerAssets.length === 0 ? (
              <p className="text-[11px] text-slate-500 text-center py-8">
                No assets in this project match that search.
              </p>
            ) : (
              <div className="space-y-2">
                {pickerAssets.map((asset) => {
                  const TypeIcon = getTypeIcon(asset.type);
                  const alreadyAttached = attachedIds.has(asset.id);

                  return (
                    <div
                      key={asset.id}
                      className="flex items-start gap-2 p-2 rounded-lg bg-slate-900/60 border border-slate-800"
                    >
                      <div className="p-1.5 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 shrink-0">
                        <TypeIcon className="w-3.5 h-3.5" />
                      </div>

                      <div className="min-w-0 flex-1">
                        <p
                          className="text-[11px] font-semibold text-slate-200 truncate"
                          title={assetLabel(asset)}
                        >
                          {assetLabel(asset)}
                        </p>
                        <div className="flex flex-wrap items-center gap-1.5 mt-1">
                          <Badge variant="violet" size="sm">
                            {asset.type_label}
                          </Badge>
                          <Badge variant={getAssetStatusVariant(asset.status)} size="sm">
                            {asset.status_label}
                          </Badge>
                        </div>
                      </div>

                      {/* An already associated asset is shown as attached and
                          offers no attach action. This is UX only: the server
                          still rejects a repeat, and is the one that has to. */}
                      {alreadyAttached ? (
                        <span className="flex items-center gap-1 text-[10px] text-emerald-400 shrink-0">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          Attached
                        </span>
                      ) : (
                        <Button
                          variant="outline"
                          size="sm"
                          disabled={busyAssetId === asset.id}
                          onClick={() => handleAttach(asset)}
                          icon={<Plus className="w-3.5 h-3.5" />}
                          className="shrink-0"
                        >
                          Attach
                        </Button>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            {pickerTotal > 0 && (
              <div className="flex items-center justify-between gap-2 pt-2 border-t border-slate-800">
                <span className="text-[10px] text-slate-500">
                  Page {page} of {pickerLastPage} - {pickerTotal} asset
                  {pickerTotal === 1 ? '' : 's'}
                </span>
                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    disabled={page <= 1 || pickerLoading}
                    onClick={() => setPage((current) => Math.max(1, current - 1))}
                    icon={<ChevronLeft className="w-3.5 h-3.5" />}
                    className="text-slate-400"
                  >
                    Prev
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    disabled={page >= pickerLastPage || pickerLoading}
                    onClick={() => setPage((current) => current + 1)}
                    icon={<ChevronRight className="w-3.5 h-3.5" />}
                    className="text-slate-400"
                  >
                    Next
                  </Button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-2 pl-4 border-l border-slate-800">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Link2 className="w-3 h-3 text-slate-500" />
          <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
            Candidates
          </span>
          <Badge variant="slate" size="sm">
            {assets.length}
          </Badge>
        </div>

        <Button
          variant="ghost"
          size="sm"
          icon={<Plus className="w-3.5 h-3.5" />}
          onClick={() => {
            setPickerError(null);
            setPickerOpen(true);
          }}
          className="text-slate-400 hover:text-white"
        >
          Attach
        </Button>
      </div>

      {error && (
        <div className="flex items-start gap-2 p-2 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-[10px]">
          <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
          <span>{error}</span>
        </div>
      )}

      {loading ? (
        <div className="flex items-center gap-2 py-2 text-[10px] text-slate-500">
          <Loader2 className="w-3 h-3 animate-spin" />
          Loading candidates...
        </div>
      ) : assets.length === 0 ? (
        <p className="text-[10px] text-slate-500">
          No candidates yet. Attach an existing asset from this project.
        </p>
      ) : (
        <div className="space-y-1.5">{assets.map(renderAttachedRow)}</div>
      )}

      {renderPicker()}
    </div>
  );
};
