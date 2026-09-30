import React from 'react';
import { Card } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Asset } from '../../types';
import { getAssetStatusVariant, formatFileSize, formatDimensions } from './AssetCard';
import { X, FileText } from 'lucide-react';

export interface AssetDetailModalProps {
  asset: Asset;
  onClose: () => void;
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
 * Read only. There is no playback, no download button, and no preview url:
 * no file exists behind this metadata yet, so anything that rendered media
 * would be showing the user something that is not their asset.
 */
export const AssetDetailModal: React.FC<AssetDetailModalProps> = ({ asset, onClose }) => {
  const fileSize = formatFileSize(asset.file_size);
  const dimensions = formatDimensions(asset);

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

        <div className="space-y-1">
          <DetailRow label="Asset ID" value={asset.id} />
          <DetailRow label="Project" value={asset.content_project_id} />
          <DetailRow label="Created" value={asset.created_at ? new Date(asset.created_at).toLocaleString() : null} />
          <DetailRow label="Updated" value={asset.updated_at ? new Date(asset.updated_at).toLocaleString() : null} />
        </div>

        <DetailGroup title="File">
          <DetailRow label="File Name" value={asset.file_name} />
          <DetailRow label="MIME Type" value={asset.mime_type} />
          <DetailRow label="File Size" value={fileSize} />
        </DetailGroup>

        <DetailGroup title="Media">
          <DetailRow
            label="Duration"
            value={
              asset.duration_seconds !== null && asset.duration_seconds !== undefined
                ? `${asset.duration_seconds} seconds`
                : null
            }
          />
          <DetailRow label="Dimensions" value={dimensions} />
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
      </Card>
    </div>
  );
};
