import React, { useState } from 'react';
import { Card } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Asset, AssetStatus } from '../../types';
import { assetService } from '../../services/assetService';
import { getAssetStatusVariant } from './AssetCard';
import {
  formatFileSize,
  formatDimensions,
  formatDuration,
  formatAspectRatioLabel,
} from '../../utils/assetFormat';
import { AssetPreview } from './AssetPreview';
import { X, FileText, RefreshCw, Loader2, AlertCircle, ArrowRight } from 'lucide-react';

export interface AssetDetailModalProps {
  projectId: number;
  asset: Asset;
  onClose: () => void;
  onAnalyzed: (asset: Asset) => void;
  onStatusChanged: (asset: Asset) => void;
}

interface DetailRowProps {
  label: string;
  value: React.ReactNode;
}

/**
 * A row is omitted entirely when the metadata is absent, rather than rendered
 * as an empty or "N/A" cell. A blank here means nothing was ever recorded,
 * which is the normal state for this resource, and filling the panel with
 * placeholders would imply more than the row supports.
 */
const DetailRow: React.FC<DetailRowProps> = ({ label, value }) => {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  return (
    <div className="flex items-start justify-between gap-4 py-1.5">
      <span className="text-[11px] text-slate-500 shrink-0">{label}</span>
      <span className="text-[11px] text-slate-200 text-right break-all">{value}</span>
    </div>
  );
};

const DetailGroup: React.FC<{ title: string; children: React.ReactNode }> = ({
  title,
  children,
}) => (
  <div className="space-y-1 pt-4 border-t border-slate-800/60">
    <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
      {title}
    </h4>
    {children}
  </div>
);

/**
 * How the measurement is going, in the user's terms.
 *
 * A failure shows the reason because "something went wrong" gives the user
 * nothing to act on, and they are the one who can fix it by uploading a
 * different file.
 */
const METADATA_NOTES: Record<string, { variant: 'emerald' | 'amber' | 'rose'; label: string }> = {
  completed: { variant: 'emerald', label: 'Measured' },
  pending: { variant: 'amber', label: 'Not measured yet' },
  failed: { variant: 'rose', label: 'Could not be measured' },
};

/**
 * Prefers the server's own message over the axios status line. AxiosError's top
 * level message is only "Request failed with status code 422", which would hide
 * the one thing the user needs here: which transition was refused.
 */
function readErrorMessage(error: unknown, fallback: string): string {
  const response = (
    error as { response?: { data?: { message?: string } } } | null
  )?.response?.data;

  return response?.message || fallback;
}

export const AssetDetailModal: React.FC<AssetDetailModalProps> = ({
  projectId,
  asset,
  onClose,
  onAnalyzed,
  onStatusChanged,
}) => {
  const [analyzing, setAnalyzing] = useState(false);
  const [analyzeError, setAnalyzeError] = useState<string | null>(null);
  const [transitioningTo, setTransitioningTo] = useState<AssetStatus | null>(null);
  const [statusError, setStatusError] = useState<string | null>(null);

  const fileSize = formatFileSize(asset.file_size);
  const dimensions = formatDimensions(asset.width, asset.height);
  const duration = formatDuration(asset.duration_seconds);
  const aspectRatio = formatAspectRatioLabel(asset.aspect_ratio);
  const metadataNote = asset.metadata_status ? METADATA_NOTES[asset.metadata_status] : null;
  const canAnalyze = Boolean(asset.has_file);
  const transitions = asset.allowed_transitions ?? [];

  const handleAnalyze = async () => {
    setAnalyzing(true);
    setAnalyzeError(null);

    try {
      const refreshed = await assetService.analyze(projectId, asset.id);
      onAnalyzed(refreshed);
    } catch (error) {
      setAnalyzeError(
        readErrorMessage(
          error,
          'The file could not be analyzed. Check that it is still attached and try again.',
        ),
      );
    } finally {
      setAnalyzing(false);
    }
  };

  const handleTransition = async (status: AssetStatus) => {
    setTransitioningTo(status);
    setStatusError(null);

    try {
      onStatusChanged(await assetService.transitionStatus(projectId, asset.id, status));
    } catch (error) {
      setStatusError(
        readErrorMessage(
          error,
          'The status could not be changed. Reopen this panel to see where the asset is now, then try again.',
        ),
      );
    } finally {
      setTransitioningTo(null);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <Card variant="default" className="max-w-2xl w-full p-6 border-slate-800 shadow-2xl my-8">
        <div className="flex items-start justify-between gap-4 mb-5">
          <div className="flex items-start gap-3 min-w-0">
            <div className="p-2.5 rounded-xl bg-violet-500/10 text-violet-400 border border-violet-500/20 shrink-0">
              <FileText className="w-5 h-5" />
            </div>
            <div className="min-w-0">
              <h3 className="text-base font-bold text-white break-words">
                {asset.title || asset.file_name || `Asset #${asset.id}`}
              </h3>
              <div className="flex flex-wrap items-center gap-2 mt-2">
                <Badge variant="violet" size="sm">
                  {asset.type_label}
                </Badge>
                <Badge variant={getAssetStatusVariant(asset.status)} size="sm">
                  {asset.status_label}
                </Badge>
              </div>
            </div>
          </div>

          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            icon={<X className="w-4 h-4" />}
            className="text-slate-400 hover:text-white shrink-0"
          >
            Close
          </Button>
        </div>

        {asset.description && (
          <p className="text-xs text-slate-300 leading-relaxed">{asset.description}</p>
        )}

        {asset.has_file && <AssetPreview projectId={projectId} asset={asset} />}

        <div className="space-y-1">
          <DetailRow label="Asset ID" value={asset.id} />
          <DetailRow label="Project" value={asset.content_project_id} />
          <DetailRow label="Created" value={asset.created_at ? new Date(asset.created_at).toLocaleString() : null} />
          <DetailRow label="Updated" value={asset.updated_at ? new Date(asset.updated_at).toLocaleString() : null} />
        </div>

        <DetailGroup title="File">
          <DetailRow label="Attached" value={asset.has_file ? 'Yes' : null} />
          <DetailRow label="File Name" value={asset.file_name} />
          <DetailRow label="MIME Type" value={asset.mime_type} />
          <DetailRow label="File Size" value={fileSize} />
        </DetailGroup>

        <DetailGroup title="Technical Metadata">
          <DetailRow
            label="Status"
            value={
              metadataNote ? (
                <Badge variant={metadataNote.variant} size="sm">
                  {metadataNote.label}
                </Badge>
              ) : null
            }
          />
          {/* An image with no width is a broken measurement, not a missing
              optional field, so only a real dimension is ever shown. */}
          <DetailRow label="Dimensions" value={dimensions} />
          <DetailRow label="Duration" value={duration ? `${asset.duration_seconds} seconds` : null} />
          <DetailRow label="Aspect Ratio" value={aspectRatio} />
          <DetailRow
            label="Measured At"
            value={asset.metadata_analyzed_at ? new Date(asset.metadata_analyzed_at).toLocaleString() : null}
          />
          {asset.metadata_error && (
            <div className="flex items-start gap-2 py-1.5 text-[11px] text-amber-400/90">
              <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-px" />
              <span>{asset.metadata_error}</span>
            </div>
          )}
        </DetailGroup>

        <DetailGroup title="Source">
          <DetailRow label="Source" value={asset.source_name} />
          <DetailRow label="Source URL" value={asset.source_url} />
          <DetailRow label="License" value={asset.license_type} />
          <DetailRow label="Attribution" value={asset.attribution} />
        </DetailGroup>

        <DetailGroup title="Notes">
          <DetailRow label="Notes" value={asset.notes} />
        </DetailGroup>

        {transitions.length > 0 && (
          <DetailGroup title="Lifecycle">
            <p className="text-[11px] text-slate-500 pb-2">
              Status records where this asset sits in the media pipeline, so moving it changes what the
              rest of the workflow can do with it. Only the steps allowed from here are offered.
            </p>
            <div className="flex flex-wrap gap-2 pt-1">
              {transitions.map((transition) => (
                <Button
                  key={transition.status}
                  variant="secondary"
                  size="sm"
                  onClick={() => handleTransition(transition.status)}
                  disabled={transitioningTo !== null}
                  icon={
                    transitioningTo === transition.status ? (
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                      <ArrowRight className="w-3.5 h-3.5" />
                    )
                  }
                >
                  {transitioningTo === transition.status ? 'Moving' : transition.label}
                </Button>
              ))}
            </div>
          </DetailGroup>
        )}

        {statusError && (
          <div className="flex items-start gap-2 p-3 mt-4 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <span>{statusError}</span>
          </div>
        )}

        {analyzeError && (
          <div className="flex items-start gap-2 p-3 mt-4 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <span>{analyzeError}</span>
          </div>
        )}

        {canAnalyze && (
          <div className="flex justify-end mt-5 pt-4 border-t border-slate-800/60">
            <Button
              variant="secondary"
              size="sm"
              onClick={handleAnalyze}
              disabled={analyzing}
              icon={analyzing ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RefreshCw className="w-3.5 h-3.5" />}
            >
              {analyzing ? 'Analyzing' : 'Analyze'}
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
};